<?php

declare(strict_types=1);

/**
 * Creates and validates one consumer fragment using explicit application paths.
 *
 * @copyright Copyright (c) 2026 Felipe Sayao Lobato Abreu <github@mentordosnerds.com>
 * @license https://opensource.org/licenses/MIT MIT License
 */

use FastForward\Changelog\Container\ServiceProvider\ChangelogServiceProvider;
use FastForward\Changelog\Fragment\FragmentWriterInterface;
use FastForward\Changelog\Release\Factory\ReleaseOptionsFactoryInterface;
use FastForward\Changelog\Validation\CheckServiceInterface;

use function FastForward\Container\container;

$autoload = $argv[1] ?? '';
$directory = realpath($argv[2] ?? '');
if ('' === $autoload || !is_file($autoload) || !isset($argv[2]) || false === $directory || !is_dir($directory)) {
    fwrite(STDERR, "Usage: php contribution.php /consumer/vendor/autoload.php /existing/project\n");
    exit(2);
}
require $autoload;

$container = container(ChangelogServiceProvider::class);
$options = $container->get(ReleaseOptionsFactoryInterface::class)->create([
    'workingDirectory' => $directory,
    'source' => 'tags',
]);
$path = $container->get(FragmentWriterInterface::class)->add(
    $options,
    'Preserves meaningful Markdown spaces when parsing fragments.',
    category: 'fixed',
    name: 'php-api-example.md',
);
$expected = "---\ncategory: fixed\ntype: patch\n---\n\nPreserves meaningful Markdown spaces when parsing fragments.\n";
if ($expected !== file_get_contents($path)) {
    throw new RuntimeException('The fragment does not match the expected application output.');
}
if (!$container->get(CheckServiceInterface::class)->check($options)->isValid()) {
    throw new RuntimeException('The pending fragment inventory is invalid.');
}
fwrite(STDOUT, "Contribution example PASS\n");
