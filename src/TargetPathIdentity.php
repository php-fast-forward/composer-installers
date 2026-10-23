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
 */

namespace FastForward\ComposerInstallers;

use Composer\Util\Filesystem;
use RuntimeException;

/** Resolves target paths and binds manifest ownership to an explicit destination. */
final class TargetPathIdentity
{
    private readonly string $root;

    /** Bind target identities to the absolute Composer project root. */
    public function __construct(string $root, private readonly Filesystem $filesystem)
    {
        if (! $filesystem->isAbsolutePath($root)) {
            $root = (getcwd() ?: '.') . '/' . $root;
        }
        $this->root = $filesystem->normalizePath($root);
    }

    /** Resolve a mapping to the normalized absolute path used for all file operations. */
    public function resolve(string $path): string
    {
        return $this->filesystem->normalizePath(
            $this->filesystem->isAbsolutePath($path) ? $path : $this->root . '/' . $path,
        );
    }

    /** Preserve the root separator when a destination is outside the project. */
    public function display(string $absolutePath): string
    {
        $absolutePath = $this->resolve($absolutePath);
        if ($absolutePath === $this->root) {
            return '.';
        }

        $prefix = rtrim($this->root, '/') . '/';

        return str_starts_with($absolutePath, $prefix)
            ? substr($absolutePath, \strlen($prefix))
            : $absolutePath;
    }

    /**
     * Record whether a target is absolute or project-relative, without guessing on read.
     *
     * @return array{target-path: string, target-path-format: string}
     */
    public function record(string $absolutePath): array
    {
        $path = $this->display($absolutePath);

        return [
            'target-path' => $path,
            'target-path-format' => $this->filesystem->isAbsolutePath($path) ? 'absolute' : 'project-relative',
        ];
    }

    /**
     * Decode an explicit identity before ownership authorizes a write or deletion.
     *
     * Legacy records can contain either a real relative path or an absolute path
     * with its leading slash lost. No filesystem probe can prove which was intended.
     *
     * @param array<string, mixed> $manifest
     *
     * @throws RuntimeException When the stored target is ambiguous or inconsistent.
     */
    public function fromManifest(array $manifest): string
    {
        $path = $manifest['target-path'] ?? null;
        $format = $manifest['target-path-format'] ?? null;
        if (! \is_string($path) || $path === '' || ! \in_array($format, ['absolute', 'project-relative'], true)) {
            throw new RuntimeException(
                'Stored bundle target is ambiguous. Preserve the manifest and files; verify the original destination, '
                . 'then explicitly repair target-path and set target-path-format to "absolute" or "project-relative" before retrying. '
                . 'Do not infer a destination by adding or removing a leading slash.',
            );
        }

        $isAbsolute = $this->filesystem->isAbsolutePath($path);
        if (($format === 'absolute') !== $isAbsolute) {
            throw new RuntimeException('Stored bundle target-path disagrees with target-path-format; verify the original destination before retrying.');
        }

        $target = $this->resolve($path);
        if ($format === 'project-relative' && $this->record($target)['target-path-format'] !== 'project-relative') {
            throw new RuntimeException('Stored project-relative bundle target escapes the project; verify the original destination before retrying.');
        }

        return $target;
    }

    /**
     * Reject retargeting before any stale-file deletion, copy or manifest replacement.
     *
     * @param array<string, mixed> $manifest
     *
     * @throws RuntimeException When ownership belongs to a different or ambiguous target.
     */
    public function requireSameTarget(array $manifest, string $target): void
    {
        if ($manifest === []) {
            return;
        }

        $previous = $this->fromManifest($manifest);
        if ($previous !== $this->resolve($target)) {
            throw new RuntimeException(\sprintf(
                'Bundle target changed from "%s" to "%s". No materialized files or manifest were changed. '
                . 'Restore the old installer-paths mapping, back up managed edits, uninstall the bundle to clean its recorded target, '
                . 'then select the new mapping and reinstall. Do not delete the manifest to bypass ownership checks.',
                $previous,
                $target,
            ));
        }
    }
}
