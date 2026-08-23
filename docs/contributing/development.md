# Development

This guide is for contributors changing the package source. Work in a clone with PHP 8.3+ and Composer; Node
22 is needed only for front-end assets. Never point the test process at an application database.

## The repository

```
config/         griglia.php (published to the host app)
database/       migrations (tables + settings defaults)
docs/           this site (see Building this site)
resources/      views, css/js sources, lang/{en,it}, sample theme pack
routes/         the package routes
src/            Livewire components, models, console commands, support classes
tests/          orchestra/testbench + phpunit
```

## Working on it

```bash
composer install
composer qa                        # lint + test + docs:check: the whole gate, in CI's order
composer lint                      # Laravel Pint + Larastan level 5
composer format                    # applies the style instead of reporting it
composer test                      # testbench, sqlite in memory
vendor/bin/testbench serve         # a bare Laravel app with the package mounted
npm install && npm run build       # precompiled assets → public/build
vendor/bin/testbench griglia:docs-build --strict # the documentation site
```

The review lifecycle regression suite is in `tests/Feature/ReviewWorkflowTest.php`. It exercises both the legacy
completion path without a reviewer and the complete submit, approve, request-changes and resubmit paths, including
invalid state transitions. `tests/Feature/ReviewUiTest.php` covers assigning the optional reviewer in the task modal.

The suite covers migrations, per-user scoping, the Livewire components, `griglia:check` / `griglia:watch`,
the theme registry and zip packs, translation parity between `en` and `it`, and the broadcast event.
GitHub Actions tests the full PHP 8.3/8.4 and Laravel 12/13 matrix. Separate jobs exercise the lowest supported
dependency versions on PHP 8.3 and Laravel 12, run the suite against MySQL 8, and reject known vulnerable Composer
dependencies with `composer audit`; Pint and PHPStan run in their own lint job. Local tests continue to use in-memory SQLite unless `GRIGLIA_TEST_DB=mysql` and
the standard `DB_*` variables select a MySQL database.

### The suite never runs against a real database

`RefreshDatabase` and Testbench's workbench (`workbench: install: true` runs `migrate:fresh`) drop **every table**
of the connection they are given, and a process started inside an application container inherits that
application's `DB_*` variables — which is how the origin project lost its board data on 2026-08-22.
`Alle80\Griglia\Testing\DatabaseGuard`, armed by the service provider whenever the process is phpunit or the
Testbench skeleton, therefore inspects every connection as it is opened and aborts unless the driver is SQLite or
the database name contains `test` (`griglia_test`, the name CI uses, passes). Set `GRIGLIA_ALLOW_PROD_DB=1` only if
a test database really cannot follow the convention.

In practice: run `vendor/bin/phpunit` where no `DB_*` variables point at a live database, and give
`vendor/bin/testbench` an explicit connection — an `env:` block in `testbench.yaml` does not override real
environment variables:

```bash
docker exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: … vendor/bin/testbench griglia:docs-generate --check
```

The `Todo`, `Checklist`, `Ingredient`, and `Question` models include package factories for focused tests:

```php
$list = Checklist::factory()->create();
$todo = Todo::factory()->for($list)->create();
$ingredient = Ingredient::factory()->for($todo)->create();
$question = Question::factory()->for($todo)->create();
```

The models resolve their package factory namespace directly, so no factory-name resolver is required in the host
application or in Testbench.

`composer lint` runs formatting checks first and then `vendor/bin/phpstan analyse` on `src/`. Larastan is configured
at level 5 without a baseline. The small exception list in `phpstan-ignores.neon` documents framework inference
gaps individually, each scoped to one identifier and one file; `reportUnmatchedIgnoredErrors` is on, so an
exception that stops matching fails the analysis instead of silently becoming permanent debt. The whole policy,
and what to do when a check fails, is on [Quality standards](quality.md).

## Releasing

Every change goes in `CHANGELOG.md` (Keep a Changelog, with a **Security** section when relevant), and a
`vX.Y.Z` tag is the release: Packagist publishes it and the GitHub Release is generated from that changelog
section. What a version number promises, what counts as public, and the four steps of cutting a release are on
[Versioning and releases](releases.md).

See also [Quality standards](quality.md) — the bar a change has to clear and what each CI job protects —
[Contributing](contributing.md) and [Building this site](docs-site.md).

## Verify before opening a change

Run `composer qa` (lint, suite, generated pages) and `composer docs:build` for the site. Expect zero
Pint/PHPStan errors, a green PHPUnit suite, and a strict bilingual docs build. Use an explicit SQLite connection
if the shell inherits application `DB_*` variables, as described above. Record any missing prerequisite instead
of reporting an unexecuted check as verified.
