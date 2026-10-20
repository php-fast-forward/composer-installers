# Contributing

Include one unique `.changelog/*.md` fragment for each observable change. Use
PHP 8.5+ and [CPX](https://github.com/laravel/cpx) to run the isolated tooling;
the library itself does not gain a runtime dependency:

```sh
cpx --skip-local fast-forward/changelog add "Describe the change." --category=fixed --type=patch --no-interaction
cpx --skip-local fast-forward/changelog check --no-interaction
# Compare committed changes with the contribution base:
cpx --skip-local fast-forward/changelog check --since=origin/main --no-interaction
```

Select the category and semantic impact that describe the change. Keep published
history intact; pending notes belong in fragments rather than an `Unreleased`
section. The project skill is installed at
[`.agents/skills/fast-forward-changelog/SKILL.md`](.agents/skills/fast-forward-changelog/SKILL.md).
Check its local integrity with
`cpx --skip-local fast-forward/changelog skill:install --check --json`.

The [workflow](.github/workflows/changelog.yml) follows the published `v0` channel.
It validates PR notes, maintains PR feedback and prepares one version PR on
`changelog/version`.
Review that PR's version, history and consumed fragments before merging.
After that approved version PR is merged into `main`, automatic publication
creates its version tag and GitHub Release. The workflow never merges PRs.

When trying the skill's PHP API example in a disposable target, validate that
target explicitly: add `--cwd=/absolute/project` to the subsequent `check`
command. This avoids checking the source repository instead of the example's
output.
