# Optional installation of fast-forward-composer-installers

`fast-forward/composer-installers:^0.4` includes this complete package under
`resources/skills/fast-forward-composer-installers`. Run `composer show fast-forward/composer-installers`
and inspect that path before copying. Earlier releases are outside this skill
contract. Composer suggestions do not install anything automatically. Library
installation, discovery-root copies and session activation are separate steps.

## Team project with llm/skills

Use Composer 2 and optionally add `llm/skills:^1.15` as development tooling.
Start with a project target for reviewable team configuration. Preserve existing
settings/aliases/approvals when merging these keys into `skills.json` **before**
installing or enabling the plugin:

```json
{
  "target": ".agents/skills",
  "aliases": [],
  "discovery": false,
  "auto-sync": true,
  "vendor-sources": false,
  "dependencies": {
    "composer": {
      "trusted": ["fast-forward/composer-installers"],
      "trusted-replace": true
    }
  }
}
```

This replaces implicit dependency trust with a narrow donor allowlist, disables
remote vendor sources and requests no host aliases. A deliberate
`fast-forward/*` allowlist grants broader organizational trust. Use the actual
host's project discovery root; Claude Code can choose `.claude/skills`.
Do not erase intentional aliases or other donors in an existing manifest.

If the application owner has authorized this plugin's execution, merge its
root approval, install it and inspect the report:

```sh
composer config allow-plugins.llm/skills true
composer require --dev 'llm/skills:^1.15'
composer skills:show
```

The donor only suggests the tool; it neither requires it at runtime nor grants
this approval on behalf of consumers. Commit the chosen manifest, skills.json,
application lockfile and intended project copies according to project policy.
Approved Composer install/update operations then perform automatic sync.

For manual timing, choose `auto-sync:false` before installation and use:

```sh
composer skills:show
composer skills:update fast-forward/composer-installers --dry-run
composer skills:update fast-forward/composer-installers
```

Explicitly naming a donor selects/trusts it for that run. Test automatic trust
without a package argument when that policy is what matters. With Composer
2.10.3 and llm/skills 1.15.0, `--no-scripts` alone still permits automatic copy;
`auto-sync:false` disables automatic sync and `--no-plugins` stops plugin loading.
Verify these behaviors again after changing tool versions.

## Update and removal boundaries

A 1.15 sync overwrites incoming same-path files. It retains extra local files
and files removed from the donor's next version. Removing a donor or uninstalling
the plugin does not delete its existing instruction copies. Regard the copy as
donor-owned; keep deliberate local instructions in a separate skill name.
Record the installed donor/tool versions and review diffs before each refresh.

Inspect cleanup without applying it:

```sh
composer skills:update --clean --dry-run
```

An unscoped clean can remove all target skills, including handwritten ones.
Scoped clean reinstalls the current selection; it cannot locate an absent donor
by historical ownership. Inventory affected directories, back up outside
discovery, and obtain authority before any destructive cleanup. Do not add a
root-wide clean hook.

## Guarded manual copying

Choose the existing project root, then copy all files together:

```sh
skill_source="vendor/fast-forward/composer-installers/resources/skills/fast-forward-composer-installers"
skill_target=".agents/skills/fast-forward-composer-installers"
test -f "$skill_source/SKILL.md" &&
test ! -e "$skill_target" &&
test ! -L "$skill_target" &&
mkdir -p "$(dirname "$skill_target")" &&
cp -R "$skill_source" "$skill_target"
```

Both existing directories and dangling links are refused. Global use is an
explicit alternative: set skill_target to
`$HOME/.agents/skills/fast-forward-composer-installers` for Codex or the configured
`$HOME/.claude/skills/fast-forward-composer-installers` for Claude Code.
Do not create a symlink into replaceable vendor content. A globally installed
Composer plugin does not constitute a global instruction copy.

For a selected manual update/removal, first move only that package into a unique
backup outside discovery:

```sh
skill_backup="$HOME/.agent-skill-backups/fast-forward-composer-installers-$(date +%Y%m%d%H%M%S)-$$"
test -d "$skill_target" &&
test ! -L "$skill_target" &&
test ! -e "$skill_backup" &&
test ! -L "$skill_backup" &&
mkdir -p "$(dirname "$skill_backup")" &&
mv "$skill_target" "$skill_backup"
```

For an update review the new source, then repeat the guarded copy. To roll back,
archive any replacement and restore the saved whole directory to the same
target. A manager-owned copy may return on its next sync until the consumer
changes that donor's trust/dependency configuration. Keep backups out of every
discovery directory.

## Optional Skills CLI

Requires npm, Node.js 22.20.0+ and Git for repository sources. The reviewed pin
is skills@1.7.2; evaluate version changes separately. Choose only the actual host:

```sh
npx --yes skills@1.7.2 add ./vendor/fast-forward/composer-installers/resources/skills/fast-forward-composer-installers --skill fast-forward-composer-installers --agent codex --copy --yes
npx --yes skills@1.7.2 list --agent codex
```

Use `--agent claude-code` for that host. Add `--global` to both commands only
when global scope was selected. Preserve/commit intended project copies and the
CLI's project lockfile. For a remote source first set SKILL_REF to a reviewed
immutable commit:

```sh
npx --yes skills@1.7.2 add "https://github.com/php-fast-forward/composer-installers/tree/$SKILL_REF/resources/skills/fast-forward-composer-installers" --skill fast-forward-composer-installers --agent codex --copy --yes
```

Review the new revision and back up local edits before repeating for an update.
Remove the chosen package/scope with:

```sh
npx --yes skills@1.7.2 remove fast-forward-composer-installers --agent codex --yes
# Only for a selected global copy:
npx --yes skills@1.7.2 remove fast-forward-composer-installers --agent codex --global --yes
```

After every route inspect the complete files, refresh the agent session,
confirm the public name is discovered and perform a bounded consumer task.
A copy, listing and successful task are separate observations.

Tool contracts: [llm/skills 1.15.0](https://github.com/roxblnfk/skills/tree/1.15.0),
[Composer plugin approval](https://getcomposer.org/doc/06-config.md#allow-plugins)
and [Skills CLI](https://github.com/vercel-labs/skills).

The Skills CLI 1.7.2 removal report is not proof that the canonical project
`.agents/skills` directory disappeared. A Codex project exercise reported one
removal while that copied package remained. Inspect the actual target and CLI
lockfiles afterwards. If an obsolete copy remains, verify that no other host
uses it, then archive only that selected directory outside discovery using the
manual recovery procedure above. Do not run a blanket clean to compensate.
