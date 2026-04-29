<?php

declare(strict_types=1);

namespace FastForward\ComposerInstallers;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use React\Promise\PromiseInterface;

final class ResourceBundleInstaller extends LibraryInstaller
{
    public const string PACKAGE_TYPE = 'fast-forward-resource-bundle';

    private ResourceBundleMaterializer $materializer;

    public function __construct(IOInterface $io, Composer $composer)
    {
        parent::__construct($io, $composer, self::PACKAGE_TYPE);

        $this->materializer = new ResourceBundleMaterializer($composer, $io);
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $promise = parent::install($repo, $package);

        return $this->after($promise, function () use ($package): void {
            $this->materializer->materialize($package, $this->getInstallPath($package));
        });
    }

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

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->materializer->remove($package);

        return parent::uninstall($repo, $package);
    }

    /**
     * @param callable(): void $callback
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
