<?php

declare(strict_types=1);

namespace FastForward\ComposerInstallers;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use RuntimeException;

final class ResourceBundleMaterializer
{
    private const string BUNDLE_EXTRA_KEY = 'fast-forward-bundle';

    private const string INSTALLER_PATHS_KEY = 'installer-paths';

    private const string PAYLOAD_PATH_KEY = 'payload-path';

    private readonly Filesystem $filesystem;

    private readonly string $vendorDir;

    private readonly string $manifestDir;

    private readonly string $rootDir;

    public function __construct(
        private readonly Composer $composer,
        private readonly IOInterface $io,
    ) {
        $this->filesystem = new Filesystem();
        $vendorDir = rtrim((string) $composer->getConfig()->get('vendor-dir', Config::RELATIVE_PATHS), '/');
        $configSource = $composer->getConfig()->getConfigSource()->getName();
        $rootDir = \is_string($configSource) && is_file($configSource)
            ? \dirname($configSource)
            : (getcwd() ?: '.');

        if (str_starts_with($vendorDir, '/')) {
            $this->vendorDir = $vendorDir;
            $this->rootDir = $rootDir;
            $this->manifestDir = $this->join($this->rootDir, 'vendor/fast-forward/.composer-installers');

            return;
        }

        $this->rootDir = $rootDir;
        $this->vendorDir = $this->join($this->rootDir, $vendorDir);
        $this->manifestDir = $this->join($this->rootDir, 'vendor/fast-forward/.composer-installers');
    }

    public function materialize(PackageInterface $package, string $installPath): void
    {
        $payloadPath = $this->payloadPath($package);
        $source = $this->join($installPath, $payloadPath);
        $target = $this->targetPath($package);

        if (! is_dir($source)) {
            throw new RuntimeException(\sprintf(
                'Fast Forward resource bundle "%s" payload path "%s" does not exist.',
                $package->getPrettyName(),
                $payloadPath,
            ));
        }

        $previousManifest = $this->readManifest($package);
        $nextEntries = $this->scanPayload($source);

        $this->removeStaleEntries($previousManifest['entries'] ?? [], $nextEntries, $target);
        $this->copyEntries($source, $target, $nextEntries, $previousManifest['entries'] ?? []);
        $this->writeManifest($package, [
            'package' => $package->getPrettyName(),
            'payload-path' => $payloadPath,
            'target-path' => $this->relativePath($target),
            'entries' => $nextEntries,
        ]);

        $this->io->writeError(\sprintf(
            '<info>Materialized %s resource payload into %s.</info>',
            $package->getPrettyName(),
            $this->relativePath($target),
        ), true, IOInterface::VERBOSE);
    }

    public function remove(PackageInterface $package): void
    {
        $manifest = $this->readManifest($package);
        $targetPath = $manifest['target-path'] ?? null;
        $entries = $manifest['entries'] ?? [];

        if (! \is_string($targetPath) || ! \is_array($entries)) {
            return;
        }

        $target = $this->absolutePath($targetPath);
        $this->removeEntries($entries, $target);

        $manifestPath = $this->manifestPath($package);
        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }
    }

    /**
     * @return array<string, array{type: string, hash?: string}>
     */
    private function scanPayload(string $source): array
    {
        $entries = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $relativePath = $this->normalizePath(substr($path, \strlen($source) + 1));

            if ($item->isDir()) {
                $entries[$relativePath] = ['type' => 'dir'];

                continue;
            }

            if ($item->isFile()) {
                $entries[$relativePath] = [
                    'type' => 'file',
                    'hash' => hash_file('sha256', $path) ?: '',
                ];
            }
        }

        ksort($entries);

        return $entries;
    }

    /**
     * @param array<string, array{type: string, hash?: string}> $entries
     * @param array<string, array{type: string, hash?: string}> $previousEntries
     */
    private function copyEntries(string $source, string $target, array $entries, array $previousEntries): void
    {
        foreach ($entries as $relativePath => $entry) {
            $sourcePath = $this->join($source, $relativePath);
            $targetPath = $this->join($target, $relativePath);

            if ('dir' === $entry['type']) {
                if (is_file($targetPath) || is_link($targetPath)) {
                    throw $this->conflict($targetPath);
                }

                if (! is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }

                continue;
            }

            if (file_exists($targetPath) && ! isset($previousEntries[$relativePath])) {
                throw $this->conflict($targetPath);
            }

            $parent = \dirname($targetPath);
            if (! is_dir($parent)) {
                mkdir($parent, 0777, true);
            }

            if (! copy($sourcePath, $targetPath)) {
                throw new RuntimeException(\sprintf('Failed to copy "%s" to "%s".', $sourcePath, $targetPath));
            }
        }
    }

    /**
     * @param array<string, array{type: string, hash?: string}> $previousEntries
     * @param array<string, array{type: string, hash?: string}> $nextEntries
     */
    private function removeStaleEntries(array $previousEntries, array $nextEntries, string $target): void
    {
        $staleEntries = array_diff_key($previousEntries, $nextEntries);
        $this->removeEntries($staleEntries, $target);
    }

    /**
     * @param array<string, array{type: string, hash?: string}> $entries
     */
    private function removeEntries(array $entries, string $target): void
    {
        uksort($entries, static fn(string $left, string $right): int => substr_count($right, '/') <=> substr_count($left, '/'));

        foreach ($entries as $relativePath => $entry) {
            $targetPath = $this->join($target, $relativePath);

            if ('file' === $entry['type'] && is_file($targetPath)) {
                unlink($targetPath);

                continue;
            }

            if ('dir' === $entry['type'] && is_dir($targetPath) && $this->filesystem->isDirEmpty($targetPath)) {
                rmdir($targetPath);
            }
        }
    }

    private function targetPath(PackageInterface $package): string
    {
        $rootExtra = $this->composer->getPackage()->getExtra();
        $installerPaths = $rootExtra[self::INSTALLER_PATHS_KEY] ?? [];

        if (! \is_array($installerPaths)) {
            throw new RuntimeException('Root extra.installer-paths must be an object mapping paths to package matches.');
        }

        foreach ($installerPaths as $path => $matches) {
            if (! \is_array($matches)) {
                continue;
            }

            foreach ($matches as $match) {
                if ($this->matchesPackage((string) $match, $package)) {
                    return $this->absolutePath($this->replaceVariables((string) $path, $package));
                }
            }
        }

        throw new RuntimeException(\sprintf(
            'No root extra.installer-paths entry matches Fast Forward resource bundle "%s".',
            $package->getPrettyName(),
        ));
    }

    private function matchesPackage(string $match, PackageInterface $package): bool
    {
        return $match === $package->getName()
            || $match === $package->getPrettyName()
            || $match === 'type:' . ResourceBundleInstaller::PACKAGE_TYPE;
    }

    private function payloadPath(PackageInterface $package): string
    {
        $extra = $package->getExtra();
        $metadata = $extra[self::BUNDLE_EXTRA_KEY] ?? [];

        if (! \is_array($metadata) || ! isset($metadata[self::PAYLOAD_PATH_KEY])) {
            throw new RuntimeException(\sprintf(
                'Fast Forward resource bundle "%s" must declare extra.%s.%s.',
                $package->getPrettyName(),
                self::BUNDLE_EXTRA_KEY,
                self::PAYLOAD_PATH_KEY,
            ));
        }

        $payloadPath = $this->normalizePath((string) $metadata[self::PAYLOAD_PATH_KEY]);
        if ('' === $payloadPath || str_starts_with($payloadPath, '/') || str_contains($payloadPath, '..')) {
            throw new RuntimeException(\sprintf(
                'Fast Forward resource bundle "%s" declares an invalid payload path "%s".',
                $package->getPrettyName(),
                $payloadPath,
            ));
        }

        return $payloadPath;
    }

    /**
     * @return array{entries?: array<string, array{type: string, hash?: string}>, target-path?: string}
     */
    private function readManifest(PackageInterface $package): array
    {
        $manifestPath = $this->manifestPath($package);
        if (! is_file($manifestPath)) {
            return [];
        }

        $contents = file_get_contents($manifestPath);
        if (! \is_string($contents)) {
            return [];
        }

        $manifest = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($manifest) ? $manifest : [];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeManifest(PackageInterface $package, array $manifest): void
    {
        $manifestPath = $this->manifestPath($package);
        $manifestDirectory = \dirname($manifestPath);

        if (! is_dir($manifestDirectory)) {
            mkdir($manifestDirectory, 0777, true);
        }

        file_put_contents(
            $manifestPath,
            json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . "\n",
        );
    }

    private function manifestPath(PackageInterface $package): string
    {
        return $this->join(
            $this->manifestDir,
            str_replace('/', '_', $package->getName()) . '.json',
        );
    }

    private function replaceVariables(string $path, PackageInterface $package): string
    {
        [$vendor, $name] = explode('/', $package->getPrettyName(), 2) + ['', $package->getPrettyName()];

        return strtr($path, [
            '{$name}' => $name,
            '{$vendor}' => $vendor,
            '{$vendor-dir}' => $this->vendorDir,
        ]);
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return rtrim($path, '/');
        }

        return rtrim($this->rootDir . '/' . $path, '/');
    }

    private function relativePath(string $path): string
    {
        $path = $this->normalizePath($path);
        $root = $this->normalizePath($this->rootDir) . '/';

        if (str_starts_with($path, $root)) {
            return substr($path, \strlen($root));
        }

        return $path;
    }

    private function join(string $left, string $right): string
    {
        return rtrim($left, '/') . '/' . ltrim($right, '/');
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', trim($path, '/'));
    }

    private function conflict(string $path): RuntimeException
    {
        return new RuntimeException(\sprintf(
            'Refusing to overwrite consumer-owned path "%s". Remove it or let the installer manage it first.',
            $this->relativePath($path),
        ));
    }
}
