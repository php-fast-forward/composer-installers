# CLI maintenance

Load for release previews, history maintenance, recovery or authorized
publication. First select the real release and inspect `list --raw` and
`help <command>` as described in the skill. Read `docs/cli-fragments.md` and the
relevant maintenance/publication guide from that complete release tag. Verify
that its PHP and Git requirements match the environment. Do not infer arguments
or current behavior from an older installed skill.

## Execute the selected CLI

Run from the application's directory. `CHANGELOG_VERSION` is the verified exact
numeric product version, not a range frozen into this skill:

```sh
cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" list --raw
cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" help status
cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" help version
```

CPX keeps tool dependencies separate from the application's Composer project;
the command still operates on application files. It is not a filesystem sandbox
or a PHP runtime. CPX's own update/help/version commands concern the executor.
An existing reviewed `vendor/bin/changelog` may run the same supported arguments
without changing the application's dependencies.

When `list` offers instruction-management operations, inspect their help before
using install, check or update options. Use the requested project/global scope
and preserve local changes. Their existence is a discovered capability, not a
promise that every published changelog version contains them. Runtime updates,
instruction installation and host-session activation remain separate results.

## Preview before consolidation

For releases offering these arguments, inspect the read-only plan first:

```sh
cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" status --json --source=tags --no-interaction
cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" version --dry-run --source=tags --no-interaction
```

Review the baseline/current/next version, effective impact, accepted fragment
paths, notes and affected files. Choose the relevant maintenance branch and
historical source explicitly. A branch name alone does not establish a release
range. Check command help before treating an exit code as success or failure;
check modes may use a nonzero result for drift as well as errors.

The normal GitHub lifecycle prepares the version PR. Applying a local version
transaction requires existing consolidation authority and the same reviewed
options as the preview. Preserve exact committed input bytes, custom-template
trust requirements and any PR-resolution setting required by that release.
Verify only central history and consumed-fragment deletions are changed; no
technical plan file or new `Unreleased` section belongs in the version PR.

If interrupted, preserve the recovery journal and remaining files. Follow that
release's recovery procedure with the same inputs. Do not edit a journal, delete
newer notes or replace history merely to force a retry.

## Additional maintenance and publication

Backfill, format, notes extraction and custom templates are optional operations.
Discover their actual commands and read that release's guide before using them.
Keep published descriptions, unknown dates, existing references and pending
fragments intact as required by the operation. Executable PHP templates require
explicit trust. Preview mutations and review the resulting diff.

Publication requires separate authority for an exact approved full commit SHA.
Read that release's publication guide and command help, preview when supported,
and supply the verified target explicitly. Use the application's approved
credential mechanism. Verify the approved version, notes, consumed fragments,
tag and source commit; PR text or a moving branch is not transaction evidence.
Preparation and publication must use the same policies that affect the output.

Do not move conflicting concrete tags or overwrite divergent published notes.
After an uncertain response, inspect remote evidence and follow the selected
release's idempotent recovery route under the existing authority. Report what
was actually published or recovered separately from successful validation.
