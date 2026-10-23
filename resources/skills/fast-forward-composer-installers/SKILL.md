---
name: fast-forward-composer-installers
description: Configure Fast Forward Composer resource-bundle payloads, root target mappings and mutable ownership; verify copied files, manifests and update/removal boundaries.
license: MIT; see LICENSE
compatibility: Supports fast-forward/composer-installers ^0.5, PHP ^8.3 and composer-plugin-api ^2.6. Requires Composer 2 with explicit root plugin approval. Earlier releases are outside this contract.
metadata:
  author: Fast Forward
  api-baseline: fast-forward/composer-installers ^0.5
---

# Materialize an application's resource bundle

Inspect the application's instructions, Composer manifest/lockfile and installed
plugin version before editing. Run `composer show fast-forward/composer-installers`.
Use this skill for ^0.5; for an older version inspect its own contract rather
than silently upgrading the application. This is a Composer plugin, not a
container service and not the optional llm/skills installer.

## Configure the bundle and its consumer

Use PHP ^8.3, Composer 2 with plugin API ^2.6, and an application-owned target.
The payload must be ordinary reviewed files. Each resource package declares a
relative source directory. For example create `bundle/composer.json`:

```json
{
  "name": "example/assets",
  "version": "1.0.0",
  "type": "fast-forward-resource-bundle",
  "license": "MIT",
  "extra": {
    "fast-forward-bundle": {
      "payload-path": "payload",
      "install-policy": "mutable"
    }
  }
}
```

Create `bundle/payload/banner.txt` containing `Fast Forward fixture` followed by a
newline. In sibling `consumer/composer.json`, merge the root approval, package
requirements and target map with existing application settings:

```json
{
  "name": "example/consumer",
  "repositories": [
    {"type": "path", "url": "../bundle", "options": {"symlink": false}}
  ],
  "require": {
    "fast-forward/composer-installers": "^0.5",
    "example/assets": "^1.0"
  },
  "config": {
    "allow-plugins": {"fast-forward/composer-installers": true}
  },
  "extra": {
    "installer-paths": {
      "generated/fast-forward-assets/": ["example/assets"]
    }
  }
}
```

Only add that approval when the application owner authorizes executing this
plugin. The donor's metadata cannot approve itself in a consumer. The library
retains type composer-plugin; the payload donor uses
fast-forward-resource-bundle. Do not change unrelated dependencies to a resource
type.

With that project approval, run from the consumer directory:

```sh
composer install
test -f generated/fast-forward-assets/banner.txt
test -f vendor/fast-forward/.composer-installers/example_assets.json
```

The package root remains in vendor/example/assets. The target receives payload
contents, so banner.txt appears directly under generated/fast-forward-assets,
without an extra payload/ directory. Composer loads the plugin entrypoint;
no application PHP bootstrap, service provider or agent SDK is involved.

## Ownership, conflicts and errors

The root extra.installer-paths maps paths to exact package names or
type:fast-forward-resource-bundle. The first matching mapping wins. Prefer
specific package names and separate owned directories. Supported variables are
{$name}, {$vendor} and {$vendor-dir}. No matching mapping, absent payload
metadata/directory or an unknown install-policy causes a runtime failure.

Mutable is the default policy. Without an existing manifest it copies new files,
adopts byte-identical files and skips divergent consumer files with a warning.
A successful Composer exit therefore does not prove that every file was copied.
Inspect the report, target bytes and entries recorded in the manifest.

On updates, previously managed entries are refreshed and stale managed entries
are removed, including local edits to those managed files. Uninstall removes
manifest-owned files. Unmanaged files remain; nonempty directories are retained.
Back up local changes before update/removal and review the exact owned paths.
Do not present the manifest as a backup or promise that edits survive updates.

Manifests written by the target-identity fix record `target-path-format` as
`project-relative` or `absolute`. Outside-project absolute targets retain their
root separator. Changing the normalized target stops before any materialized
file or manifest mutation, even with authoritative policy. Restore the old
mapping, back up managed edits and remove the bundle through Composer to clean
its recorded destination; then select the new mapping and require it again.
Do not discard the old manifest to bypass the check. Composer may already have
updated its lockfile and package in vendor before reporting the plugin error.

Historical 0.4 and earlier installations wrote manifests without
`target-path-format`. They are outside this skill's current API baseline.
After an explicitly approved upgrade to ^0.5, updates/removal of those records
stop because a relative-looking path could
also be an absolute external path whose leading slash was lost. Preserve the
manifest and files; verify the original destination from installation records,
then explicitly repair `target-path` and add the corresponding format before
retrying. A project-relative record must stay under the project. Do not guess
from file existence or the current mapping; unresolved identity stays blocked.
See the package README's target-identity recovery procedure for the full scope.

Authoritative bundles can overwrite divergent existing files even without an
old manifest; choose that only for intentionally donor-owned targets. Do not
use an entire application source/config/discovery root as a blanket target.
Files are copied literally, while a path repository may separately symlink the
package root. Preserve the consumer's established ownership and recovery rules.

## Execute and verify the example

Complete configurations and the payload live under [examples](examples/consumer/composer.json).
Copy the examples to a disposable workspace before running Composer; do not
install vendor content into the skill's own example directory. Its bundle and
consumer directories must remain siblings.

After installation, run the standalone verifier:

```sh
php /path/to/fast-forward-composer-installers/examples/verify.php /absolute/consumer
```

It checks payload bytes and the actual manifest entry, then prints
`fast-forward-composer-installers: ok`. For a mutable conflict exercise,
use a fresh disposable consumer, precreate a different banner.txt and repeat
installation: the file must remain and must not enter the manifest. Also verify
that an invalid mapping fails rather than writing into an unrelated directory.
Never test updates/removal against production files.

For optional installation of this instruction package, load
[skill distribution and lifecycle](references/installation.md). The llm/skills
plugin manages instruction copies; it does not replace this resource-bundle API.
