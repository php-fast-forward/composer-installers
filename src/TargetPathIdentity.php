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
        if (! $this->isAbsolutePath($root)) {
            $root = (getcwd() ?: '.') . '/' . $root;
        }
        $this->root = $this->normalize($root);
    }

    /** Resolve a mapping to the normalized absolute path used for all file operations. */
    public function resolve(string $path): string
    {
        return $this->normalize(
            $this->isAbsolutePath($path) ? $path : $this->root . '/' . $path,
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
            'target-path-format' => $this->isAbsolutePath($path) ? 'absolute' : 'project-relative',
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

        $isAbsolute = $this->isAbsolutePath($path);
        if (($format === 'absolute') !== $isAbsolute) {
            throw new RuntimeException('Stored bundle target-path disagrees with target-path-format; verify the original destination before retrying.');
        }

        $target = $this->resolve($path);
        if ($format === 'project-relative' && $this->record($target)['target-path-format'] !== 'project-relative') {
            throw new RuntimeException('Stored project-relative bundle target escapes the project; verify the original destination before retrying.');
        }

        return $target;
    }

    /** Classify paths according to the host, where a colon does not root POSIX paths. */
    private function isAbsolutePath(string $path): bool
    {
        return DIRECTORY_SEPARATOR === '/'
            ? str_starts_with($path, '/')
            : $this->filesystem->isAbsolutePath($path);
    }

    /**
     * Normalize spelling only when removing parent segments preserves the destination.
     *
     * Cancelling a symlink with ".." changes physical traversal. Check each
     * cancelled component before lexical normalization, including earlier parent
     * cancellations and dot/separator aliases. Plain ../external remains valid.
     *
     * @throws RuntimeException When a parent traversal would cancel a symlink.
     */
    private function normalize(string $path): string
    {
        $prefix = '';
        foreach (explode('/', str_replace('\\', '/', $path)) as $component) {
            if ($component === '.') {
                continue;
            }
            if ($component === '..') {
                if (is_link($prefix)) {
                    throw new RuntimeException(
                        'Bundle target contains ambiguous symlink/parent traversal. No materialized files or manifest were changed. '
                        . 'Use the verified physical destination without traversing a symlink followed by ".."; '
                        . 'preserve existing ownership records before retrying.',
                    );
                }
                $prefix = $this->filesystem->normalizePath($prefix . '/..');
            } elseif ($component === '') {
                if ($prefix === '' || $prefix === '/') {
                    $prefix .= '/';
                }
            } else {
                $prefix .= ($prefix !== '' && ! str_ends_with($prefix, '/') ? '/' : '') . $component;
            }
        }

        return $this->filesystem->normalizePath($path);
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
