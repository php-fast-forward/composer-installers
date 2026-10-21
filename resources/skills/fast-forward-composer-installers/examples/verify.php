<?php

declare(strict_types=1);

$consumer = $argv[1] ?? '';
if (!is_dir($consumer)) {
    throw new RuntimeException('Pass an existing disposable consumer directory.');
}
$target = $consumer . '/generated/fast-forward-assets/banner.txt';
$manifest = $consumer . '/vendor/fast-forward/.composer-installers/example_assets.json';
if (!is_file($target) || file_get_contents($target) !== "Fast Forward fixture\n" || !is_file($manifest)) {
    throw new RuntimeException('Expected payload or manifest is missing.');
}
$record = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
if ($record['package'] !== 'example/assets' || !isset($record['entries']['banner.txt'])
    || $record['entries']['banner.txt']['hash'] !== hash_file('sha256', $target)) {
    throw new RuntimeException('Manifest does not identify the copied payload.');
}
echo "fast-forward-composer-installers: ok\n";
