# Optional PHP integration

Load only when the application needs injected services instead of the CLI or
GitHub lifecycle. CPX executes a separate tool; it does not provide an application
autoloader. Inspect the application's Composer installation with
`composer show fast-forward/changelog`, then read the PHP integration/API guide,
manifest requirements and migration notes from that exact release tag.

Use the installed namespaces and method signatures. Keep the application's
existing command loader, container precedence and secret mechanism. Expose only
the needed domain services and inject external boundaries in application tests.
Resolving a service or writing a fragment does not authorize commits or releases.

## Bundled contribution example

The complete [contribution program](../examples/contribution.php) illustrates the
`0.1.0` PHP API: explicit consumer autoloader and target directory, provider
composition, fragment creation and validation. It is an optional example, not
the contract for every later release. Before using it with another version,
compare its imports/signatures with that release's documented API or use the
example shipped with the selected release.

For a matching API, run it first in a disposable target:

```sh
php .agents/skills/fast-forward-changelog/examples/contribution.php ./vendor/autoload.php /absolute/project
vendor/bin/changelog check --no-interaction
```

Success prints `Contribution example PASS`; exactly one
`.changelog/php-api-example.md` is created, with no commit or central-history
change. A repeated run must expose the filename collision rather than overwrite
the file. Missing inputs and runtime failures remain nonzero. Do not report
success after a failed write or ignore the validation report.

Keep PR/issue/author fields absent unless verified. Service access follows the
same fragment, version-transaction and publication boundaries as the selected
release's CLI. Learn optional configuration, planner/history services and
approved-commit publication from that version's documentation when the task
requires them; do not load an API inventory for an ordinary fragment task.
