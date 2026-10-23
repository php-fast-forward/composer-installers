<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\Config;
use Composer\Config\JsonConfigSource;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Composer\Package\CompletePackage;
use Composer\Package\RootPackage;
use FastForward\ComposerInstallers\ResourceBundleMaterializer;

require dirname(__DIR__) . '/vendor/autoload.php';

/** Assert observable file and ownership outcomes without enabling PHP assertions. */
function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Write only synthetic fixture files in this run's disposable directory. */
function put(string $path, string $contents): void
{
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $contents);
}

/** Build real Composer API objects without loading global configuration or plugins. */
function fixture(string $directory, string $target, string $policy = 'mutable'): array
{
    $root = $directory . '/consumer';
    put($root . '/composer.json', '{}');
    $config = new Config(false, $root);
    $config->setConfigSource(new JsonConfigSource(new JsonFile($root . '/composer.json')));
    $composer = new Composer();
    $composer->setConfig($config);
    $consumer = new RootPackage('example/consumer', '1.0.0.0', '1.0.0');
    $consumer->setExtra(['installer-paths' => [$target => ['example/assets']]]);
    $composer->setPackage($consumer);
    $bundle = new CompletePackage('example/assets', '1.0.0.0', '1.0.0');
    $bundle->setExtra(['fast-forward-bundle' => ['payload-path' => 'payload', 'install-policy' => $policy]]);

    return [new ResourceBundleMaterializer($composer, new NullIO()), $consumer, $bundle, $root];
}

/** Capture every file's bytes so a rejected operation must have no partial writes. */
function snapshot(string $directory): array
{
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) {
            $files[substr($file->getPathname(), strlen($directory))] = file_get_contents($file->getPathname());
        }
    }
    ksort($files);
    return $files;
}

/** Require an actionable rejection before touching any managed or consumer file. */
function rejectsWithoutWrites(callable $operation, string $directory, string $diagnostic): void
{
    $before = snapshot($directory);
    $caught = null;
    try {
        $operation();
    } catch (RuntimeException $error) {
        $caught = $error;
    }
    expect($caught !== null, 'Expected rejection: ' . $diagnostic);
    expect(str_contains($caught->getMessage(), $diagnostic), 'Unexpected diagnostic: ' . $caught->getMessage());
    expect(snapshot($directory) === $before, 'Rejected operation modified fixture files.');
}

$run = dirname(__DIR__) . '/tmp/materialization-' . bin2hex(random_bytes(6));
mkdir($run, 0777, true);
$failures = [];
$cases = [];

foreach (['relative', 'absolute-inside', 'absolute-outside', 'relative-outside'] as $kind) {
    $cases[$kind . ' install/update/remove'] = static function () use ($run, $kind): void {
        $directory = $run . '/' . $kind;
        $root = $directory . '/consumer';
        $target = match ($kind) {
            'absolute-inside' => $root . '/generated/',
            'absolute-outside' => $directory . '/external/',
            'relative-outside' => '../external/',
            default => 'generated/',
        };
        $destination = str_contains($kind, 'outside') ? $directory . '/external' : $root . '/generated';
        [$materializer, , $bundle] = fixture($directory, $target);
        $source = $directory . '/bundle';
        put($source . '/payload/a.txt', 'first');
        put($source . '/payload/stale.txt', 'stale');
        put($destination . '/consumer.txt', 'preserve');
        // A damaged absolute manifest used to point removal into this decoy tree.
        $decoy = $root . '/' . ltrim($destination, '/') . '/a.txt';
        put($decoy, 'decoy');
        $materializer->materialize($bundle, $source);
        expect(file_get_contents($destination . '/a.txt') === 'first', 'Install missed selected target.');
        put($source . '/payload/a.txt', 'updated');
        unlink($source . '/payload/stale.txt');
        put($source . '/payload/added.txt', 'added');
        $materializer->materialize($bundle, $source);
        expect(file_get_contents($destination . '/a.txt') === 'updated', 'Managed update did not apply.');
        expect(!file_exists($destination . '/stale.txt'), 'Stale managed file remains.');
        expect(file_get_contents($destination . '/added.txt') === 'added', 'New payload file missing.');
        $materializer->remove($bundle);
        expect(!file_exists($destination . '/a.txt') && !file_exists($destination . '/added.txt'), 'Uninstall missed owned files.');
        expect(file_get_contents($destination . '/consumer.txt') === 'preserve', 'Uninstall removed consumer file.');
        expect(file_get_contents($decoy) === 'decoy', 'Uninstall touched project-relative decoy.');
    };
}

foreach (['mutable', 'authoritative'] as $policy) {
    $cases[$policy . ' retarget rejects then explicit cleanup succeeds'] = static function () use ($run, $policy): void {
        $directory = $run . '/retarget-' . $policy;
        [$materializer, $consumer, $bundle, $root] = fixture($directory, 'old/', $policy);
        $source = $directory . '/bundle';
        foreach (['changed', 'unchanged', 'stale'] as $name) {
            put($source . '/payload/' . $name . '.txt', $name);
        }
        $materializer->materialize($bundle, $source);
        put($root . '/old/consumer.txt', 'keep old');
        foreach (['changed', 'unchanged', 'stale', 'added'] as $name) {
            put($root . '/new/' . $name . '.txt', 'consumer ' . $name);
        }
        put($source . '/payload/changed.txt', 'updated');
        unlink($source . '/payload/stale.txt');
        put($source . '/payload/added.txt', 'new');
        $consumer->setExtra(['installer-paths' => ['new/' => ['example/assets']]]);
        rejectsWithoutWrites(static fn() => $materializer->materialize($bundle, $source), $directory, 'target changed');
        // Removal is deliberately scoped to the recorded OLD destination, even
        // when the current root mapping points elsewhere.
        $materializer->remove($bundle);
        foreach (['changed', 'unchanged', 'stale'] as $name) {
            expect(!file_exists($root . '/old/' . $name . '.txt'), 'Explicit cleanup missed old managed file.');
        }
        expect(file_get_contents($root . '/old/consumer.txt') === 'keep old', 'Old consumer file removed.');
        foreach (['changed', 'unchanged', 'stale', 'added'] as $name) {
            expect(file_get_contents($root . '/new/' . $name . '.txt') === 'consumer ' . $name, 'Cleanup touched new consumer file.');
        }
    };
}

$cases['equivalent normalized target reuses ownership'] = static function () use ($run): void {
    $directory = $run . '/equivalent';
    [$materializer, $consumer, $bundle, $root] = fixture($directory, 'generated/./');
    $source = $directory . '/bundle';
    put($source . '/payload/a.txt', 'first');
    $materializer->materialize($bundle, $source);
    $consumer->setExtra(['installer-paths' => [$root . '//generated/' => ['example/assets']]]);
    put($source . '/payload/a.txt', 'updated');
    $materializer->materialize($bundle, $source);
    expect(file_get_contents($root . '/generated/a.txt') === 'updated', 'Equivalent target lost ownership.');
};

foreach (['relative', 'stripped-absolute'] as $kind) {
    $cases['legacy ' . $kind . ' manifest fails closed'] = static function () use ($run, $kind): void {
        $directory = $run . '/legacy-' . $kind;
        [$materializer, , $bundle, $root] = fixture($directory, 'generated/');
        $source = $directory . '/bundle';
        put($source . '/payload/a.txt', 'first');
        $materializer->materialize($bundle, $source);
        $path = $root . '/vendor/fast-forward/.composer-installers/example_assets.json';
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        unset($manifest['target-path-format']);
        if ($kind === 'stripped-absolute') {
            $manifest['target-path'] = ltrim($directory . '/external', '/');
            put($directory . '/external/a.txt', 'external');
            put($root . '/' . $manifest['target-path'] . '/a.txt', 'decoy');
        }
        put($path, json_encode($manifest, JSON_THROW_ON_ERROR));
        rejectsWithoutWrites(static fn() => $materializer->materialize($bundle, $source), $directory, 'ambiguous');
        rejectsWithoutWrites(static fn() => $materializer->remove($bundle), $directory, 'ambiguous');
        // Emulate a deliberate owner repair with a verified exact destination.
        $manifest['target-path-format'] = $kind === 'relative' ? 'project-relative' : 'absolute';
        if ($kind === 'stripped-absolute') {
            $manifest['target-path'] = $directory . '/external';
        }
        put($path, json_encode($manifest, JSON_THROW_ON_ERROR));
        $materializer->remove($bundle);
        expect(!file_exists($path), 'Explicit verified cleanup did not remove manifest.');
        if ($kind === 'stripped-absolute') {
            expect(!file_exists($directory . '/external/a.txt'), 'Verified external target was not cleaned.');
            expect(file_get_contents($root . '/' . ltrim($directory . '/external', '/') . '/a.txt') === 'decoy', 'Repair touched relative decoy.');
        } else {
            expect(!file_exists($root . '/generated/a.txt'), 'Verified relative target was not cleaned.');
        }
    };
}

foreach ($cases as $name => $case) {
    try {
        $case();
        echo 'PASS ' . $name . PHP_EOL;
    } catch (Throwable $error) {
        $failures[] = $name . ': ' . $error->getMessage();
        fwrite(STDERR, 'FAIL ' . end($failures) . PHP_EOL);
    }
}
echo 'Synthetic fixtures: ' . $run . PHP_EOL;
exit($failures === [] ? 0 : 1);
