# Quality standards

This page is the bar a change has to clear before it goes in, and the list of what checks it instead of a
person remembering. If you only want the commands, run `composer qa` before opening a pull request; the rest
of this page explains what each check protects and what is deliberately left to review.

## The bar

Seven rules, in the order they usually get broken.

| # | Rule | Checked by |
|---|---|---|
| Q1 | The suite is green on every supported combination — PHP 8.3 and 8.4, Laravel 12 and 13, oldest and newest dependencies, SQLite and MySQL | CI (`tests`, `prefer-lowest`, `mysql`) |
| Q2 | A fix carries a test that fails without it; a feature carries a test for the behaviour it promises | review |
| Q3 | Zero Pint diffs and zero PHPStan errors at level 5, with no baseline | `composer lint`, CI (`lint`) |
| Q4 | Every user-visible string is translated in `en` **and** `it`, and never names an agent: `:agent`, never «Claude» | `TranslationsTest`, `DocsTranslationsTest` |
| Q5 | Documentation ships in the same commit, in both languages, and the generated reference pages match the code | `composer docs:check`, CI, `DocsGenerateTest` |
| Q6 | Every change has a `CHANGELOG.md` entry under *Unreleased* | review, pull request template |
| Q7 | No known vulnerable dependency, and no dependency added without a reason written in the pull request | CI (`security`), review |

Q2 and Q6 are the two a machine cannot judge, so they are the two the
[pull request template](https://github.com/alle80/griglia/blob/master/.github/PULL_REQUEST_TEMPLATE.md) asks
you to confirm.

## The checks you can run

```bash
composer qa            # lint + test + docs:check — what CI runs, in the same order
composer lint          # Pint --test, then PHPStan level 5 on src/
composer format        # applies the style instead of reporting it
composer test          # the PHPUnit suite through Testbench, SQLite in memory
composer test:coverage # the suite plus a coverage report (needs pcov or xdebug)
composer docs:check    # the generated reference pages still match the code
composer docs:build    # the bilingual site, warnings treated as errors (needs MkDocs)
```

`composer qa` needs nothing but PHP and Composer, which is why it is the gate: the strict site build is a
separate command because it needs the Python toolchain of
[Building this site](docs-site.md). Run the suite where no `DB_*` variables point at a live database — the
[development guide](development.md#the-suite-never-runs-against-a-real-database) explains the guard that stops
it, and why it exists.

## What runs on GitHub

Everything below runs on every pull request and on every push to `master`
([`tests.yml`](https://github.com/alle80/griglia/blob/master/.github/workflows/tests.yml)). A run that is
superseded by a new push to the same branch is cancelled; on `master` it is never cancelled, so every commit
on the default branch keeps a verdict of its own. The workflow token is read-only.

| Job | What it protects | Locally |
|---|---|---|
| `PHP × Laravel` (4 jobs) | the supported matrix, and the generated docs | `composer test`, `composer docs:check` |
| `prefer-lowest` | the *lower* bound of every constraint — the version a conservative host application actually installs | `composer update --prefer-lowest --prefer-stable && composer test` |
| `lint` | style and static analysis | `composer lint` |
| `mysql` | the queries that SQLite forgives and MySQL 8 does not | `GRIGLIA_TEST_DB=mysql … composer test` |
| `security` | known vulnerabilities in the dependency tree | `composer audit` |

[`docs.yml`](https://github.com/alle80/griglia/blob/master/.github/workflows/docs.yml) publishes the site to
GitHub Pages on every push to `master` that touches the documentation. It builds with `--strict`, so a broken
link fails the deploy instead of shipping.

## Static analysis policy

PHPStan runs at **level 5** on `src/`, with Larastan, and **without a baseline**. A baseline hides existing
debt behind a green run and grows quietly; the small exception list in `phpstan-ignores.neon` is the
alternative, and it is deliberately uncomfortable to use:

- every entry is scoped to one error identifier **and** one file, so it cannot quietly cover a different
  problem somewhere else;
- `reportUnmatchedIgnoredErrors` stays on, so an exception that stops matching — because the code was fixed,
  or the file was deleted — **fails** the analysis instead of becoming permanent debt;
- each entry says which framework inference gap it works around, in a comment above its group.

Raising the level is welcome as its own pull request, never as a side effect of a feature.

## Tests

The suite runs through `orchestra/testbench` on in-memory SQLite, and covers migrations, per-user scoping, the
Livewire components, the `griglia:check` and `griglia:watch` contracts, plans and reviews, themes, settings,
notifications, translation parity, and the community files of the repository itself.

- **A bug fix starts with a failing test.** «It cannot be tested» usually means the seam is in the wrong
  place — say so in the pull request and it gets discussed, not waved through.
- **The command contract is a public API.** `griglia:check` output and options are what every agent depends
  on: changing a line means changing `GrigliaCheckCommandTest` on purpose, never by accident.
- **Repository files are tested too.** `CONTRIBUTING.md`, the code of conduct, the issue forms, the scripts
  and this quality setup have their own tests, because a broken YAML form is silent — GitHub just stops
  offering it.
- **No test touches a real database.** `DatabaseGuard` aborts on any connection that is neither SQLite nor a
  database whose name contains `test`.

### Coverage

Coverage is a **tool, not a gate**. `composer test:coverage` prints it and writes an HTML report under
`build/coverage`, which is the right way to find an untested branch you forgot. It needs pcov or xdebug; at
the time of writing the suite covers about 83% of the lines of `src/`. There is no minimum
percentage in CI on purpose: the number is easy to raise without testing anything (a test that exercises a
class and asserts nothing counts), and a threshold turns review attention into arithmetic. What is enforced
instead is Q2 — a change comes with a test that fails without it — which is the property a percentage is a
proxy for.

## Dependencies

- Runtime constraints stay **wide** (`^12.0|^13.0`): the host application resolves them, so narrowing one is a
  breaking change for somebody. `prefer-lowest` in CI is what keeps the lower bound honest.
- A new runtime dependency needs a reason in the pull request; things that are useful but not required belong
  in `suggest`, like `laravel/ai` and `laravel/reverb`.
- [Dependabot](https://github.com/alle80/griglia/blob/master/.github/dependabot.yml) opens grouped weekly
  pull requests for **development** dependencies and GitHub Actions, and monthly ones for the front-end
  toolchain. It never touches the runtime constraints. Its pull requests go through the same matrix as
  everybody else's.
- `composer audit` fails the build on a known vulnerability. A vulnerability in Griglia itself is not an
  issue: see [Security](../operations/security.md).

## Style and conventions

- **PHP**: Laravel Pint, the `laravel` preset, no local overrides. `composer format` fixes; CI only checks.
- **Comments explain why**, in English, and are worth the line: this codebase prefers one paragraph that
  explains a trap to five lines that restate the code.
- **English in the repository**, Italian only in the documentation site's `.it.md` pages.
- **Commits** in English with a conventional prefix (`feat:`, `fix:`, `docs:`, `chore:`, `ci:`,
  `refactor:`), one logical change per branch.
- **No agent name in a string**: the board is agent-neutral, and `:agent` is the placeholder.

## When a check fails

| Symptom | What it usually is |
|---|---|
| `docs:check` fails | you changed a command, a config key or a setting: rerun `vendor/bin/testbench griglia:docs-generate` and commit the pages |
| Only `prefer-lowest` fails | you used something that exists in the newest dependency but not in the oldest one you declare |
| Only `mysql` fails | strict mode, an ambiguous column, a `GROUP BY`, or a column length SQLite ignores |
| Only one PHP version fails | a version-specific deprecation |
| PHPStan fails after a rebase | an ignore entry in `phpstan-ignores.neon` no longer matches — delete it, do not raise its count |

Nothing here is bureaucracy for its own sake: each rule exists because its absence has cost somebody time
already. If one of them is in the way of a good change, say so in the pull request — the standard can move,
silently skipping it cannot.

See also [Contributing](contributing.md), [Development](development.md) and [Governance](governance.md).
