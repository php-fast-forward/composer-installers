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
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use React\Promise\PromiseInterface;

/**
 * Installs Composer packages that expose Fast Forward resource payloads.
 *
 * The package root remains under Composer's vendor directory while the declared
 * payload is materialized into the consumer path selected by the root package.
 */
final class ResourceBundleInstaller extends LibraryInstaller
{
    /**
     * Composer package type handled by this installer.
     */
    public const string PACKAGE_TYPE = 'fast-forward-resource-bundle';

    /**
     * Copies and tracks resource payloads after Composer installs package code.
     */
    private ResourceBundleMaterializer $materializer;

    /**
     * Creates the installer for Fast Forward resource bundle packages.
     */
    public function __construct(IOInterface $io, Composer $composer)
    {
        parent::__construct($io, $composer, self::PACKAGE_TYPE);

        $this->materializer = new ResourceBundleMaterializer($composer, $io);
    }

    /**
     * Installs the package and materializes its resource payload.
     *
     * @return PromiseInterface|null
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $promise = parent::install($repo, $package);

        return $this->after($promise, function () use ($package): void {
            $this->materializer->materialize($package, $this->getInstallPath($package));
        });
    }

    /**
     * Updates the package and refreshes the materialized resource payload.
     *
     * @return PromiseInterface|null
     */
    public function update(
        InstalledRepositoryInterface $repo,
        PackageInterface $initial,
        PackageInterface $target,
    ) {
        $promise = parent::update($repo, $initial, $target);

        return $this->after($promise, function () use ($target): void {
            $this->materializer->materialize($target, $this->getInstallPath($target));
        });
    }

    /**
     * Removes the materialized payload and delegates package removal to Composer.
     *
     * @return PromiseInterface|null
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->materializer->remove($package);

        return parent::uninstall($repo, $package);
    }

    /**
     * Runs a callback after a Composer promise resolves, or immediately when no
     * promise exists.
     *
     * @param callable(): void $callback
     *
     * @return PromiseInterface|null
     */
    private function after(?PromiseInterface $promise, callable $callback): ?PromiseInterface
    {
        if ($promise instanceof PromiseInterface) {
            return $promise->then(static function () use ($callback): void {
                $callback();
            });
        }

        $callback();

        return null;
    }
}
