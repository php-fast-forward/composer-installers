---
name: fast-forward-changelog
description: Adopt Fast Forward Changelog's GitHub lifecycle, write and check independent fragments, review version PRs, and perform authorized release maintenance. Discover the selected release's documentation and CLI capabilities before choosing integration details.
license: MIT
compatibility: Requires access to the application repository and the selected release's documentation. Verify that release's runner or PHP requirements before execution. Local CLI examples use CPX; PHP integration uses the application's Composer installation. GitHub credentials are needed only for authorized remote operations.
metadata:
  author: php-fast-forward
  source: original
  repository: https://github.com/php-fast-forward/changelog
  api-baseline: Verify the selected published release; commands and integration details are release-specific.
---

# Fast Forward Changelog

Ordinary contributions add independent `.changelog/*.md` fragments. GitHub
automation checks each contribution, maintains PR feedback and prepares a
reviewable version PR. That PR consolidates the accepted fragments into
`CHANGELOG.md` and deletes only the consumed files. Publication follows a
separate approval for the exact result; a green check or prepared PR is not that
approval.

## Select the real contract

1. Read application instructions, Git status and its existing changelog setup.
   Use its reviewed version for an ordinary task; do not upgrade it incidentally.
   If its ref is a moving channel, resolve the current commit and corresponding
   published full tag before selecting a local runtime. `v0` is not Composer `0`.
2. For new adoption or a requested update, discover the latest published stable
   release in the [official repository](https://github.com/php-fast-forward/changelog/releases).
   Confirm the complete release tag and its commit, exclude drafts/prereleases,
   and respect any explicitly selected maintenance line. For CPX or PHP use,
   confirm the same version is available on
   [Packagist](https://packagist.org/packages/fast-forward/changelog).
3. Read that tag's README and relevant documentation, not `main` or an old copy
   of this skill. Before adding a workflow, inspect its
   `resources/workflows/changelog.yml`, companion README and the reusable workflow
   they reference. Copy the release's caller, review inputs/permissions and set
   its product references to that verified full tag or immutable release commit;
   do not retain an obsolete embedded pin blindly. After setting the final ref,
   inspect the reusable workflow at that exact ref and confirm its inputs,
   permissions and runner requirements before saving the application caller.
   If that release offers a project `install` command, inspect its help and prefer
   its guided preview for a missing integration. Explain the release branch,
   publication default and optional skill before accepting writes.
   The lifecycle defaults to `publish: true`; confirm this at the selected ref.
   Its automatic route publishes a version tag and GitHub Release
   after the reviewed managed PR is merged; it does not merge the PR. A
   validation-only trial must choose `publish: false` explicitly. Installation must
   not publish anything. Preserve an existing caller, its pin and settings;
   migration or upgrades need their own task scope.
4. For local CLI work, set `CHANGELOG_VERSION` to the verified exact numeric
   release, without the tag's `v`. Discover commands and inspect their arguments:

   ```sh
   cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" list --raw
   cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" help add
   cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" help check
   ```

   Use [CPX's official setup](https://github.com/laravel/cpx) when installation is
   authorized; do not pin the executor in these commands. `--skip-local` selects
   its separate tool installation. CPX may handle `--help` or `--version` itself,
   so use `help <command>` for changelog help. An existing reviewed project binary
   may be used directly instead. Confirm prerequisites from the chosen release.

Installing this skill installs instructions, independently of the runtime. If
the selected CLI offers skill installation/check/update operations, inspect
their help and use only the supported operation and requested scope. Otherwise
follow that release's skill-installation guide; do not invent a command.

Native installation can record the source package version, commit and payload
integrity. Compare an installed copy with the selected CLI through its documented
check operation; do not confuse that local comparison with a latest-version
lookup. Preserve NPX or other installer ownership and its lockfile rather than
trying to adopt the copy or rewrite metadata into a merely similar JSON format.

This procedure avoids a frozen API inventory. A breaking release can still
change the lifecycle or these entrypoints: when documentation and capabilities
disagree, resolve the mismatch before writes rather than guessing compatibility.

## Write and check a contribution

Read the change and describe its observable effect. For example, create one
unique `.changelog/preserve-markdown-spaces.md`:

```markdown
---
category: fixed
type: patch
---

Preserves meaningful Markdown spaces when parsing fragments.
```

Keep existing notes and Markdown intact. Use the selected release's fragment
schema. For the established category scheme, `added`, `changed` and `deprecated`
default to `minor`, `removed` to `major`, and `fixed`/`security` to `patch`.
Persist the effective type explicitly; a justified override may express a
compatible dependency change as `changed`/`patch`. Apply the application's
versioning policy deliberately, especially during `0.x` development.

After help confirms these arguments, the CLI can create and validate that note
from the application directory instead of writing the example manually:

```sh
cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" add "Preserves meaningful Markdown spaces when parsing fragments." --category=fixed --no-interaction
cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" check --no-interaction
```

Verify that exactly one intended fragment was created and central history stayed
unchanged. Do not run both authoring routes for the same note. Fix diagnostics
and rerun the check. Before a committed PR comparison, inspect `help check`,
fetch the intended baseline under existing authority, then use its supported
comparison option, for example:

```sh
cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" check --since=origin/main --no-interaction
```

That comparison requires committed contribution changes. A local uncommitted
fragment can pass local validation without satisfying the PR comparison.

Optional PR/issue/author metadata must come from verified evidence. When the
origin PR is already known and supported, use `--pull-request=<number>` or
`pull_request: <number>` after confirming its repository and exact head. Leave
unknown fields absent; do not invent a number or require a second fragment
commit after opening a PR. If the selected release resolves PR origins during
compilation, follow its documented policy consistently across preparation and
publication. No qualifying PR should become an invented commit or author link.

Ordinary work preserves inherited fragments and published history. Do not add a
new `Unreleased` section. Editorial replacement, history maintenance and release
consolidation require their own task scope.

## Verify the lifecycle

For adoption, open a small pilot PR and inspect the exact head's required status,
sticky comment and independent application checks. Exercise a missing or
malformed note and any enabled bot route. Inspect the generated version PR:
planned version/impact, exact notes, central-history changes and only the accepted
fragment deletions. Review again after its head changes. The workflow does not
merge its own version PR.

For the selected release, verify how committed history and ancestral tags define
the version baseline. The current lifecycle advances from the greatest stable
version in either source: tag `v1.7.0`, committed history through `1.7.1` and a
new patch produce `1.7.2`. Skipping publication does not block the next PR or
authorize retroactive publication of `1.7.1`. Preserve earlier history and review
the proposed next version; do not create missing tags as an incidental repair.

Load only the reference needed for the task:

- [Automation](references/automation.md): selected caller, optional settings,
  credentials, maintenance lines and pilot verification.
- [CLI maintenance](references/cli.md): previews, consolidation, history,
  interrupted transactions and separately authorized publication.
- [PHP integration](references/php-api.md): release-specific application
  services and the bundled example, only when programmatic integration is needed.

Retain authority already given, but do not infer commit, push, merge, installation
elsewhere or publication authority from an ordinary fragment task. Report changed
paths, executed checks and the verified version/PR/SHA relevant to the result.
