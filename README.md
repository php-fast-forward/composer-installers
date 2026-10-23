# Fast Forward Composer Installers

<p align="center">
  <img src="docs/_static/mascot-banner.png" alt="Dash installing package resources into consumer directories" width="840">
</p>

Composer installer plugin for Fast Forward resource bundle packages.

`fast-forward/composer-installers` lets Fast Forward packages declare a payload
directory and lets consumer roots choose where that payload is copied. The
package root still lives in `vendor/`, but only the declared payload contents
are materialized into consumer-owned paths.

## Resource Package Metadata

Resource packages use the `fast-forward-resource-bundle` type and declare the
payload directory to copy. They may also declare how existing target files are
handled when the manifest is missing:

```json
{
  "type": "fast-forward-resource-bundle",
  "extra": {
    "fast-forward-bundle": {
      "payload-path": ".agents",
      "install-policy": "mutable"
    }
  }
}
```

`install-policy` is optional and defaults to `mutable`.

## Consumer Configuration

Consumer repositories allow this Composer plugin and map resource packages to
target directories:

```json
{
  "config": {
    "allow-plugins": {
      "fast-forward/composer-installers": true
    }
  },
  "extra": {
    "installer-paths": {
      ".agents/": ["fast-forward/agents"],
      ".github/workflows/": ["fast-forward/github-workflows"]
    }
  }
}
```

The target path receives the payload contents. For `fast-forward/agents`, that
means `.agents/agents` and `.agents/skills` are copied into the consumer project
without creating `.agents/agents/.agents`.

## Update Behavior

The installer writes a manifest under `vendor/fast-forward/.composer-installers`
for each materialized package. On package update, files listed in the manifest
are refreshed from the new payload and stale managed files are removed. Files
that already exist in the target but are not tracked by the manifest are handled
according to the bundle install policy.

The `mutable` policy is the default. It adopts existing files when their content
already matches the payload and recreates the manifest, which makes `composer
install` safe after deleting `vendor/`. If an existing file differs from the
payload and no manifest marks it as managed, that path is skipped with a warning
instead of overwriting a consumer customization. The installer still materializes
the remaining payload entries and records only copied or adopted entries in the
manifest.

The `authoritative` policy is intended for generated or shared automation such
as GitHub workflow bundles. It overwrites existing divergent target files and
then writes a fresh manifest, allowing committed workflow files to be refreshed
after a clean clone where `vendor/` has not been installed yet. Authoritative
bundles should use package-specific target directories or clearly owned file
names, such as a `fast-forward-` prefix, so the installer only overwrites paths
that are intentionally controlled by the bundle. Non-empty consumer directories
are not removed automatically; they are skipped with a warning.

The materialized payload is copied as literal files and directories. Composer
`path` repositories may still symlink the package root in `vendor/`, but the
consumer-facing payload remains copied.

## Target identity and deliberate target changes

Relative mappings resolve under the Composer project; absolute mappings may be
inside or outside it. Manifests preserve that identity with `target-path` and
`target-path-format` (`project-relative` or `absolute`). Equivalent normalized
paths share ownership. The format describes the recorded path: an absolute
mapping inside the project can be recorded as project-relative, while an
outside-project destination keeps its absolute root separator.

Updating a bundle with a different target stops before any materialized file or
manifest is changed, under either install policy. Entries from the old target
never authorize overwrites or stale-file deletion in the new one. Composer may
already have updated its lockfile and the package in `vendor/` when the plugin
reports the error; the diagnostic concerns the materialized payload and manifest,
not a rollback of the entire Composer transaction.

To change a target deliberately, restore the old mapping, back up any edits to
managed files, and remove the resource bundle through Composer. Removal cleans
only the old manifest's managed entries and retains unrelated files and nonempty
directories. Then change the mapping and require the bundle again. Inspect
mutable conflict warnings at the new target. Do not delete the manifest as a
shortcut: that would lose the evidence needed to clean the old target.

### Existing manifests without a target format

Earlier manifests could lose an external absolute path's leading slash. A stored
path such as `tmp/example/assets` could mean either a project-relative directory
or the damaged absolute `/tmp/example/assets`. Both might exist, so checking for
files or consulting the current mapping does not establish which was originally
owned. Updates and removals stop on **all** manifests lacking an explicit format,
including ordinary relative records, without guessing or modifying payloads.

For a legacy consumer, preserve a backup of its manifest and affected files.
Verify the original destination from the installation's configuration and records;
if it cannot be established, leave the operation blocked. After that verification,
repair `target-path` to the verified path and add `target-path-format` explicitly:
use `project-relative` for a path under the project, or `absolute` with the full
rooted path for an external destination. Preserve the manifest's entries. Retry
the unchanged-target update, or deliberately remove the bundle to clean that
verified destination before retargeting. The plugin does not migrate real consumer
manifests automatically.

## Focused regression checks

After `composer install`, run:

```sh
composer validate --strict
php tests/materialization-regressions.php
php tests/composer-path-lifecycle.php
```

The first runner uses real Composer API objects and synthetic files. The second
executes the installed Composer CLI against disposable local `path` repositories,
with Packagist disabled, scripts disabled and an isolated Composer home/cache.
It exercises install, update, rejected retargeting and deliberate removal for
relative and absolute destinations. Both retain their synthetic fixtures under
the ignored `tmp/` directory for inspection; they never operate on real consumers.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for changelog fragments, local checks and the release-review flow.

## Consumer agent skill

The package includes [fast-forward-composer-installers](resources/skills/fast-forward-composer-installers/SKILL.md) for application integration.
It supports `fast-forward/composer-installers:^0.5`; inspect the installed version.
See [skill installation and lifecycle](resources/skills/fast-forward-composer-installers/references/installation.md) for
optional project synchronization with `llm/skills ^1.15`, guarded manual
project/global copies and a pinned Skills CLI alternative. Shipping files does
not install tooling or activate the skill in an agent session.
