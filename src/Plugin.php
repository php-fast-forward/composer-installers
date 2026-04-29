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
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Registers the Fast Forward resource bundle installer with Composer.
 *
 * This plugin keeps the installer lifecycle aligned with Composer plugin
 * activation, deactivation, and uninstall hooks.
 */
final class Plugin implements PluginInterface
{
    /**
     * The installer registered during the active Composer plugin lifecycle.
     */
    private ?ResourceBundleInstaller $installer = null;

    /**
     * Activates the Composer plugin and registers the resource bundle installer.
     *
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->installer = new ResourceBundleInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    /**
     * Deactivates the Composer plugin and removes the registered installer.
     *
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        if (null !== $this->installer) {
            $composer->getInstallationManager()->removeInstaller($this->installer);
            $this->installer = null;
        }
    }

    /**
     * Uninstalls the Composer plugin by delegating to the deactivation routine.
     *
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $this->deactivate($composer, $io);
    }
}
