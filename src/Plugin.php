<?php

declare(strict_types=1);

namespace FastForward\ComposerInstallers;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

final class Plugin implements PluginInterface
{
    private ?ResourceBundleInstaller $installer = null;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->installer = new ResourceBundleInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        if (null !== $this->installer) {
            $composer->getInstallationManager()->removeInstaller($this->installer);
            $this->installer = null;
        }
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $this->deactivate($composer, $io);
    }
}
