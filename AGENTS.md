# AGENTS.md

## Project Overview

`fast-forward/composer-installers` is a Composer plugin that materializes Fast
Forward resource-bundle payloads into consumer repositories.

The package intentionally starts small: it installs package roots through
Composer's normal vendor flow, then copies only declared payload contents into
consumer-owned target directories.

## Setup Commands

Install dependencies with:

```bash
composer install
```

## Development Workflow

Important paths:

- `src/Plugin.php` registers the Composer installer.
- `src/ResourceBundleInstaller.php` hooks Composer package install, update, and uninstall operations.
- `src/ResourceBundleMaterializer.php` copies payload files and maintains the installer manifest.
- `README.md` documents the bundle metadata and consumer configuration contract.

Do not add GitHub workflows in this repository yet; shared workflows are being
externalized separately.

## Testing Instructions

Use the smallest relevant check while editing:

```bash
composer validate --strict
```

For installer behavior, create a throwaway consumer project that requires this
repository and a local `fast-forward-resource-bundle` package through Composer
`path` repositories.

## Code Style

Keep PHP code strict, focused, and side-effect conscious. Do not silently
overwrite consumer-owned files; only refresh files tracked by the installer
manifest.
