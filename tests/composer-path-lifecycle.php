<?php

declare(strict_types=1);

/** Test actual Composer plugin lifecycle hooks using local-only disposable packages. */
function writeFixture(string $path, string $value): void
{
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $value);
}

/** Serialize synthetic package configuration without using global Composer settings. */
function writeConfiguration(string $path, array $configuration): void
{
    writeFixture($path, json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
}

/** Fail independently of zend.assertions settings. */
function verify(bool $result, string $message): void
{
    if (!$result) {
        throw new RuntimeException($message);
    }
}

/** Execute Composer with local path repositories and no scripts, registry or global plugins. */
function runComposer(string $consumer, string $runtime, array $arguments, bool $success = true): string
{
    $process = proc_open(
        ['composer', '--working-dir=' . $consumer, '--no-interaction', '--no-ansi', ...$arguments],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        array_merge(getenv(), [
            'COMPOSER_HOME' => $runtime . '/home',
            'COMPOSER_CACHE_DIR' => $runtime . '/cache',
            'COMPOSER_DISABLE_NETWORK' => '1',
            'COMPOSER' => 'composer.json',
        ]),
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the installed Composer executable.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    verify(($status === 0) === $success, 'Unexpected Composer result (' . $status . '): ' . $output);
    return $output;
}

$repository = dirname(__DIR__);
$run = $repository . '/tmp/composer-lifecycle-' . bin2hex(random_bytes(6));

foreach (['relative', 'absolute-inside', 'absolute-outside', 'posix-colon'] as $kind) {
    if ($kind === 'posix-colon' && DIRECTORY_SEPARATOR !== '/') {
        continue;
    }
    $directory = $run . '/' . $kind;
    $consumer = $directory . '/consumer';
    $target = $kind === 'absolute-outside' ? $directory . '/external' : $consumer . '/generated';
    $mapping = $kind === 'relative' ? 'generated/' : $target . '/';
    if ($kind === 'posix-colon') {
        $target = $consumer . '/a:generated';
        $mapping = 'a:generated';
    }
    $bundle = [
        'name' => 'example/assets', 'version' => '1.0.0', 'type' => 'fast-forward-resource-bundle', 'license' => 'MIT',
        'extra' => ['fast-forward-bundle' => ['payload-path' => 'payload']],
    ];
    $configuration = [
        'name' => 'example/consumer', 'type' => 'project', 'license' => 'MIT',
        'repositories' => [
            ['type' => 'path', 'url' => $repository, 'options' => ['symlink' => true, 'versions' => ['fast-forward/composer-installers' => '0.5.0']]],
            ['type' => 'path', 'url' => $directory . '/bundle', 'options' => ['symlink' => false]],
            ['packagist.org' => false],
        ],
        'require' => ['fast-forward/composer-installers' => '^0.5', 'example/assets' => '^1.0'],
        'config' => ['allow-plugins' => ['fast-forward/composer-installers' => true]],
        'extra' => ['installer-paths' => [$mapping => ['example/assets']]],
    ];
    writeConfiguration($directory . '/bundle/composer.json', $bundle);
    writeConfiguration($consumer . '/composer.json', $configuration);
    writeFixture($directory . '/bundle/payload/a.txt', 'first');
    writeFixture($directory . '/bundle/payload/stale.txt', 'stale');
    writeFixture($target . '/consumer.txt', 'keep target');
    runComposer($consumer, $directory, ['update', '--no-scripts', '--no-audit', '--no-progress']);
    verify(file_get_contents($target . '/a.txt') === 'first', $kind . ': plugin did not install payload.');

    $bundle['version'] = '1.0.1';
    // Equivalent spellings must retain ownership through real update hooks.
    $configuration['extra']['installer-paths'] = [$target . '//./' => ['example/assets']];
    writeConfiguration($consumer . '/composer.json', $configuration);
    writeConfiguration($directory . '/bundle/composer.json', $bundle);
    writeFixture($directory . '/bundle/payload/a.txt', 'updated');
    unlink($directory . '/bundle/payload/stale.txt');
    writeFixture($directory . '/bundle/payload/added.txt', 'added');
    runComposer($consumer, $directory, ['update', 'example/assets', '--no-scripts', '--no-audit', '--no-progress']);
    verify(file_get_contents($target . '/a.txt') === 'updated', $kind . ': plugin did not update payload.');
    verify(!file_exists($target . '/stale.txt'), $kind . ': plugin left stale managed entry.');

    $manifestPath = $consumer . '/vendor/fast-forward/.composer-installers/example_assets.json';
    $manifest = file_get_contents($manifestPath);
    $configuration['extra']['installer-paths'] = ['new/' => ['example/assets']];
    writeConfiguration($consumer . '/composer.json', $configuration);
    foreach (['a', 'added', 'stale'] as $name) {
        writeFixture($consumer . '/new/' . $name . '.txt', 'consumer ' . $name);
    }
    $bundle['version'] = '1.0.2';
    writeConfiguration($directory . '/bundle/composer.json', $bundle);
    unlink($directory . '/bundle/payload/added.txt');
    writeFixture($directory . '/bundle/payload/a.txt', 'third');
    $output = runComposer($consumer, $directory, ['update', 'example/assets', '--no-scripts', '--no-audit', '--no-progress'], false);
    verify(str_contains($output, 'target changed'), $kind . ': missing retarget diagnostic.');
    verify(file_get_contents($manifestPath) === $manifest, $kind . ': failed update replaced manifest.');
    verify(file_get_contents($target . '/a.txt') === 'updated', $kind . ': failed update touched old payload.');
    foreach (['a', 'added', 'stale'] as $name) {
        verify(file_get_contents($consumer . '/new/' . $name . '.txt') === 'consumer ' . $name, $kind . ': failed update touched new target.');
    }

    // Deliberate old-target cleanup uses the recorded manifest, even after a
    // failed Composer update has replaced the package in vendor.
    $configuration['extra']['installer-paths'] = [$mapping => ['example/assets']];
    writeConfiguration($consumer . '/composer.json', $configuration);
    runComposer($consumer, $directory, ['remove', 'example/assets', '--no-scripts', '--no-audit', '--no-progress']);
    verify(!file_exists($target . '/a.txt') && !file_exists($target . '/added.txt'), $kind . ': plugin did not clean old managed files.');
    verify(!file_exists($manifestPath), $kind . ': plugin did not remove manifest.');
    verify(file_get_contents($target . '/consumer.txt') === 'keep target', $kind . ': plugin removed old consumer file.');
    foreach (['a', 'added', 'stale'] as $name) {
        verify(file_get_contents($consumer . '/new/' . $name . '.txt') === 'consumer ' . $name, $kind . ': cleanup touched new target.');
    }
    echo 'PASS Composer path install/update/rejected-retarget/remove: ' . $kind . PHP_EOL;
}
echo 'Synthetic consumers: ' . $run . PHP_EOL;
