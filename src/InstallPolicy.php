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

/**
 * Defines how resource bundles handle existing target files without a manifest.
 */
enum InstallPolicy: string
{
    /**
     * Existing files are adopted only when their content matches the payload.
     */
    case Mutable = 'mutable';

    /**
     * Existing files are overwritten by the payload.
     */
    case Authoritative = 'authoritative';

    /**
     * Returns the default policy used by resource bundles.
     */
    public static function default(): self
    {
        return self::Mutable;
    }

    /**
     * Checks whether this policy overwrites divergent existing files.
     */
    public function overwritesExistingFiles(): bool
    {
        return self::Authoritative === $this;
    }
}
