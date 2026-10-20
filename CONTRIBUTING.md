# Contributing

Include one unique `.changelog/*.md` fragment for each observable change. Use
PHP 8.5+ and [CPX](https://github.com/laravel/cpx) to run the isolated tooling;
the library itself does not gain a runtime dependency:

```sh
cpx --skip-local fast-forward/changelog:0.3.0 add "Describe the change." --category=fixed --type=patch --no-interaction
cpx --skip-local fast-forward/changelog:0.3.0 check --no-interaction
# Compare committed changes with the contribution base:
cpx --skip-local fast-forward/changelog:0.3.0 check --since=origin/main --no-interaction
```

Select the category and semantic impact that describe the change. Keep published
history intact; pending notes belong in fragments rather than an `Unreleased`
section. The project skill is installed at
[`.agents/skills/fast-forward-changelog/SKILL.md`](.agents/skills/fast-forward-changelog/SKILL.md).
Check its local integrity with
`cpx --skip-local fast-forward/changelog:0.3.0 skill:install --check --json`.

The pinned [workflow](.github/workflows/changelog.yml) validates PR notes,
maintains PR feedback and prepares one version PR on `changelog/version`.
Review that PR's version, history and consumed fragments before merging.
After that approved version PR is merged into `main`, automatic publication
creates its version tag and GitHub Release. The workflow never merges PRs.
