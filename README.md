# Fast Forward Composer Installers

Composer installer plugin for Fast Forward resource bundle packages.

`fast-forward/composer-installers` lets Fast Forward packages declare a payload
directory and lets consumer roots choose where that payload is copied. The
package root still lives in `vendor/`, but only the declared payload contents
are materialized into consumer-owned paths.

## Resource Package Metadata

Resource packages use the `fast-forward-resource-bundle` type and declare the
payload directory to copy:

```json
{
  "type": "fast-forward-resource-bundle",
  "extra": {
    "fast-forward-bundle": {
      "payload-path": ".agents"
    }
  }
}
```

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
that already exist in the target but are not tracked by the manifest are treated
as consumer-owned and are not silently overwritten.

The materialized payload is copied as literal files and directories. Composer
`path` repositories may still symlink the package root in `vendor/`, but the
consumer-facing payload remains copied.
