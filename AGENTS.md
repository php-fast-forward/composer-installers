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
- `src/TargetPathIdentity.php` resolves target identities and rejects ambiguous manifests or target changes before materialized-file mutation.
- `README.md` documents the bundle metadata and consumer configuration contract.

The repository consumes Fast Forward Changelog's reusable workflow through
`.github/workflows/changelog.yml` on the published `v0` channel. Add independent
`.changelog/*.md` fragments for observable changes and follow `CONTRIBUTING.md`.
Run `composer install` to fetch the Changelog skill declared in `skills.json`,
then read `.agents/skills/fast-forward-changelog/SKILL.md` for its lifecycle.
The skill is managed by `llm/skills` from GitHub; do not commit generated
`.agents` files or add a native Changelog installation receipt.

Review the managed version PR before merging. Its approved merge into `main`
publishes the version tag and GitHub Release automatically. Adoption work must
not merge a version PR or publish a release without separate authorization.

## Testing Instructions

Use the smallest relevant check while editing:

```bash
composer validate --strict
```

For installer behavior, create a throwaway consumer project that requires this
repository and a local `fast-forward-resource-bundle` package through Composer
`path` repositories.

Run `php tests/materialization-regressions.php` for focused ownership/path
regressions and `php tests/composer-path-lifecycle.php` for actual Composer
install/update/remove hooks in synthetic local path consumers. Fixtures remain
under ignored `tmp/`; preserve real consumer data. Legacy manifests without
`target-path-format` require explicit owner verification, never guessed repair.

## Code Style

Keep PHP code strict, focused, and side-effect conscious. Do not silently
overwrite consumer-owned files; only refresh files tracked by the installer
manifest.

## Bundled consumer skill

Read [the consumer skill](resources/skills/fast-forward-composer-installers/SKILL.md) when changing application integration
examples, consumer guides or the skill. Keep its version contract, references
and executable examples aligned with the supported public API. The canonical
distributable source is `resources/skills`; it needs no installed copy or host
alias. Repository maintenance continues to follow this AGENTS.md.
