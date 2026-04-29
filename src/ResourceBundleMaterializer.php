<?php

declare(strict_types=1);

/**
 * Composer installer plugin for Fast Forward resource bundles.
 *
 * This file is part of fast-forward/composer-installers project.
 *
 * @author   Felipe Sayao Lobato Abreu <github@mentordosnerds.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 *
 * @see      https://github.com/php-fast-forward/composer-installers
 * @see      https://github.com/php-fast-forward/composer-installers/issues
 * @see      https://datatracker.ietf.org/doc/html/rfc2119
 */

namespace FastForward\ComposerInstallers;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use JsonException;
use RuntimeException;

/**
 * Copies resource bundle payloads into consumer-selected target directories.
 *
 * Materialized files are tracked in a manifest so package updates can refresh
 * managed files and remove stale managed paths without deleting local consumer
 * files.
 */
final class ResourceBundleMaterializer
{
    /**
     * Resource package metadata key used to configure bundle behavior.
     */
    private const string BUNDLE_EXTRA_KEY = 'fast-forward-bundle';

    /**
     * Root package metadata key used to map packages to target paths.
     */
    private const string INSTALLER_PATHS_KEY = 'installer-paths';

    /**
     * Resource package metadata key containing the payload directory.
     */
    private const string PAYLOAD_PATH_KEY = 'payload-path';

    /**
     * Resource package metadata key containing the existing-file policy.
     */
    private const string INSTALL_POLICY_KEY = 'install-policy';

    /**
     * Existing target files are adopted only when they match the payload.
     */
    private const string INSTALL_POLICY_MUTABLE = 'mutable';

    /**
     * Existing target files are overwritten by the payload.
     */
    private const string INSTALL_POLICY_AUTHORITATIVE = 'authoritative';

    /**
     * Default existing-file policy for resource bundles.
     */
    private const string DEFAULT_INSTALL_POLICY = self::INSTALL_POLICY_MUTABLE;

    /**
     * Filesystem helper shared with Composer internals.
     */
    private readonly Filesystem $filesystem;

    /**
     * Absolute path to the root package vendor directory.
     */
    private readonly string $vendorDir;

    /**
     * Absolute path where installer manifests are stored.
     */
    private readonly string $manifestDir;

    /**
     * Absolute path to the root package directory.
     */
    private readonly string $rootDir;

    /**
     * Creates a materializer bound to the active Composer root package.
     */
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

    /**
     * Copies the package payload into the configured consumer target path.
     *
     * @throws JsonException
     * @throws RuntimeException When bundle metadata is invalid or files cannot be copied safely.
     *
     * @return void
     */
    public function materialize(PackageInterface $package, string $installPath): void
    {
        $payloadPath = $this->payloadPath($package);
        $installPolicy = $this->installPolicy($package);
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
        $this->copyEntries($source, $target, $nextEntries, $previousManifest['entries'] ?? [], $installPolicy);
        $this->writeManifest($package, [
            'package' => $package->getPrettyName(),
            'payload-path' => $payloadPath,
            'install-policy' => $installPolicy,
            'target-path' => $this->relativePath($target),
            'entries' => $nextEntries,
        ]);

        $this->io->writeError(\sprintf(
            '<info>Materialized %s resource payload into %s.</info>',
            $package->getPrettyName(),
            $this->relativePath($target),
        ), true, IOInterface::VERBOSE);
    }

    /**
     * Removes all paths tracked by the package manifest.
     *
     * @throws JsonException
     *
     * @return void
     */
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
     * Scans payload files and directories into manifest entries.
     *
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
     * Copies all manifest entries from the source payload into the consumer target.
     *
     * @param array<string, array{type: string, hash?: string}> $entries
     * @param array<string, array{type: string, hash?: string}> $previousEntries
     *
     * @throws RuntimeException When a consumer-owned path would be overwritten or a file copy fails.
     *
     * @return void
     */
    private function copyEntries(
        string $source,
        string $target,
        array $entries,
        array $previousEntries,
        string $installPolicy,
    ): void {
        foreach ($entries as $relativePath => $entry) {
            $sourcePath = $this->join($source, $relativePath);
            $targetPath = $this->join($target, $relativePath);

            if ('dir' === $entry['type']) {
                if (is_file($targetPath) || is_link($targetPath)) {
                    if (self::INSTALL_POLICY_AUTHORITATIVE !== $installPolicy) {
                        throw $this->conflict($targetPath, $installPolicy);
                    }

                    unlink($targetPath);
                }

                if (! is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }

                continue;
            }

            if ((file_exists($targetPath) || is_link($targetPath)) && ! isset($previousEntries[$relativePath])) {
                if ($this->targetFileMatchesEntry($targetPath, $entry)) {
                    continue;
                }

                if (self::INSTALL_POLICY_AUTHORITATIVE !== $installPolicy) {
                    throw $this->conflict($targetPath, $installPolicy);
                }
            }

            $parent = \dirname($targetPath);
            if (! is_dir($parent)) {
                mkdir($parent, 0777, true);
            }

            $this->prepareFileTarget($targetPath, $installPolicy);

            if (! copy($sourcePath, $targetPath)) {
                throw new RuntimeException(\sprintf('Failed to copy "%s" to "%s".', $sourcePath, $targetPath));
            }
        }
    }

    /**
     * Removes entries that existed in the previous manifest but not in the next payload.
     *
     * @param array<string, array{type: string, hash?: string}> $previousEntries
     * @param array<string, array{type: string, hash?: string}> $nextEntries
     *
     * @return void
     */
    private function removeStaleEntries(array $previousEntries, array $nextEntries, string $target): void
    {
        $staleEntries = array_diff_key($previousEntries, $nextEntries);
        $this->removeEntries($staleEntries, $target);
    }

    /**
     * Removes files and empty directories that belong to a manifest.
     *
     * @param array<string, array{type: string, hash?: string}> $entries
     *
     * @return void
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

    /**
     * Resolves the configured consumer target path for a resource bundle package.
     *
     * @throws RuntimeException When the root installer path metadata is invalid or missing.
     */
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

    /**
     * Checks whether a root installer-paths match applies to a package.
     */
    private function matchesPackage(string $match, PackageInterface $package): bool
    {
        return $match === $package->getName()
            || $match === $package->getPrettyName()
            || $match === 'type:' . ResourceBundleInstaller::PACKAGE_TYPE;
    }

    /**
     * Resolves and validates the package payload directory metadata.
     *
     * @throws RuntimeException When the package does not declare a usable payload path.
     */
    private function payloadPath(PackageInterface $package): string
    {
        $metadata = $this->bundleMetadata($package);
        if (! isset($metadata[self::PAYLOAD_PATH_KEY])) {
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
     * Resolves and validates the package install policy metadata.
     *
     * @throws RuntimeException When the package declares an unsupported install policy.
     */
    private function installPolicy(PackageInterface $package): string
    {
        $metadata = $this->bundleMetadata($package);
        $installPolicy = (string) ($metadata[self::INSTALL_POLICY_KEY] ?? self::DEFAULT_INSTALL_POLICY);

        if (! \in_array($installPolicy, [self::INSTALL_POLICY_MUTABLE, self::INSTALL_POLICY_AUTHORITATIVE], true)) {
            throw new RuntimeException(\sprintf(
                'Fast Forward resource bundle "%s" declares unsupported install policy "%s". '
                . 'Supported policies: "%s", "%s".',
                $package->getPrettyName(),
                $installPolicy,
                self::INSTALL_POLICY_MUTABLE,
                self::INSTALL_POLICY_AUTHORITATIVE,
            ));
        }

        return $installPolicy;
    }

    /**
     * Resolves the package Fast Forward resource bundle metadata.
     *
     * @return array<string, mixed>
     */
    private function bundleMetadata(PackageInterface $package): array
    {
        $extra = $package->getExtra();
        $metadata = $extra[self::BUNDLE_EXTRA_KEY] ?? [];

        if (! \is_array($metadata)) {
            throw new RuntimeException(\sprintf(
                'Fast Forward resource bundle "%s" must declare extra.%s as an object.',
                $package->getPrettyName(),
                self::BUNDLE_EXTRA_KEY,
            ));
        }

        return $metadata;
    }

    /**
     * Reads the package manifest if one was written by a previous install or update.
     *
     * @throws JsonException
     *
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
     * Writes the package manifest used by future updates and removals.
     *
     * @param array<string, mixed> $manifest
     *
     * @throws JsonException
     *
     * @return void
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

    /**
     * Resolves the manifest path for a package.
     */
    private function manifestPath(PackageInterface $package): string
    {
        return $this->join(
            $this->manifestDir,
            str_replace('/', '_', $package->getName()) . '.json',
        );
    }

    /**
     * Replaces supported Composer installer path variables for a package.
     */
    private function replaceVariables(string $path, PackageInterface $package): string
    {
        [$vendor, $name] = explode('/', $package->getPrettyName(), 2) + ['', $package->getPrettyName()];

        return strtr($path, [
            '{$name}' => $name,
            '{$vendor}' => $vendor,
            '{$vendor-dir}' => $this->vendorDir,
        ]);
    }

    /**
     * Resolves a path relative to the root package directory.
     */
    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return rtrim($path, '/');
        }

        return rtrim($this->rootDir . '/' . $path, '/');
    }

    /**
     * Converts an absolute path into a root-package-relative path when possible.
     */
    private function relativePath(string $path): string
    {
        $path = $this->normalizePath($path);
        $root = $this->normalizePath($this->rootDir) . '/';

        if (str_starts_with($path, $root)) {
            return substr($path, \strlen($root));
        }

        return $path;
    }

    /**
     * Joins two normalized path segments.
     */
    private function join(string $left, string $right): string
    {
        return rtrim($left, '/') . '/' . ltrim($right, '/');
    }

    /**
     * Normalizes directory separators and outer slashes for manifest paths.
     */
    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', trim($path, '/'));
    }

    /**
     * Checks whether a target file already matches a payload manifest entry.
     *
     * @param array{type: string, hash?: string} $entry
     */
    private function targetFileMatchesEntry(string $targetPath, array $entry): bool
    {
        return ! is_link($targetPath)
            && is_file($targetPath)
            && isset($entry['hash'])
            && hash_file('sha256', $targetPath) === $entry['hash'];
    }

    /**
     * Prepares a target path so a payload file can be copied into place.
     */
    private function prepareFileTarget(string $targetPath, string $installPolicy): void
    {
        if (is_link($targetPath)) {
            unlink($targetPath);

            return;
        }

        if (! is_dir($targetPath)) {
            return;
        }

        if (self::INSTALL_POLICY_AUTHORITATIVE === $installPolicy && $this->filesystem->isDirEmpty($targetPath)) {
            rmdir($targetPath);

            return;
        }

        throw $this->conflict($targetPath, $installPolicy);
    }

    /**
     * Creates the conflict exception used when a consumer-owned path would be overwritten.
     */
    private function conflict(string $path, string $installPolicy): RuntimeException
    {
        return new RuntimeException(\sprintf(
            'Refusing to overwrite consumer-owned path "%s" while using "%s" install policy. '
            . 'Remove it, restore the manifest, or use the "authoritative" policy for this bundle.',
            $this->relativePath($path),
            $installPolicy,
        ));
    }
}
