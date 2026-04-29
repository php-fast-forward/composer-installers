# Fast Forward Composer Installers

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
