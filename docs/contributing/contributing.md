# Contributing

Issues and pull requests are welcome at
[github.com/alle80/griglia](https://github.com/alle80/griglia). This page is the *how*: what to send, what a
change must carry, and what happens after you open it. The *what* and the *who* — mission, scope, roles,
supported versions and response times — are on the [Governance](governance.md) page. Read that one first if
your change is larger than a fix: most refused work is refused for scope, not for quality.

Griglia is maintained by one person on personal time. A short, complete contribution gets merged; a large one
that nobody discussed first usually waits.

## Ways to contribute

| You have | Send |
|---|---|
| A bug | An issue with a reproduction — see [Reporting a bug](#reporting-a-bug) |
| An idea | An issue that describes the problem before the solution — see [Proposing a change](#proposing-a-change) |
| A fix or a small feature | A pull request, tests included |
| A larger feature | An issue first, then the pull request |
| A documentation gap, a typo, a better Italian sentence | A pull request on `docs/` — the same rules, minus the tests |
| Time | Answer somebody else's issue, or confirm a bug on your setup |

GitHub opens the right form for you: **New issue** offers a bug report, an idea, a documentation problem and a
question, and each form asks for exactly what the sections below describe. Blank issues are disabled — if none
of the four fits, take the closest one and say in the first line what it really is. Pull requests arrive
prefilled with the
[template](https://github.com/alle80/griglia/blob/master/.github/PULL_REQUEST_TEMPLATE.md): the problem, what
changed, how you tested it, and the checklist at the end of this page.

A vulnerability is the exception: never a public issue, never a pull request that describes it — follow the
[security policy](../operations/security.md).

## Before you write code

1. **Search the [issues](https://github.com/alle80/griglia/issues)**, open and closed. The answer may already
   be there, including "this is out of scope".
2. **Check the scope table** in [Governance](governance.md#scope). Anything that belongs to the host
   application — authentication, user administration, the layout of your app — is not part of the package,
   however useful it is.
3. **Open an issue for anything larger than a fix** and wait for a direction. A pull request that arrives
   without one may be closed for scope alone, and that is a bad way to spend your evening.
4. **Say if you are already working on it.** One comment on the issue avoids two people writing the same
   patch.

## Reporting a bug

A good report is one someone else can reproduce. Include:

- the **Griglia version** (`composer show alle80/griglia`), the **Laravel** and **PHP** versions;
- the **mode** (`server` or `local`) and, if it matters, whether you are behind a proxy or in Docker;
- **what you did, what you expected, what happened** — in that order, three lines each is enough;
- the **error**: the exception with its stack trace, the browser console for a front-end problem, the output
  of the command for an agent-side one (`php artisan griglia:check --all`);
- a **screenshot** for anything visual, and the theme or style you were using.

The best report carries a **minimal reproduction**: a fresh Laravel application with the package installed,
or a failing test against `main`. If you cannot reproduce it outside your application, say so — a report with
an honest "only happens on my setup" is still worth opening.

The **Bug report** form asks for this list one field at a time, so filling it in from top to bottom is the
fastest way to write a report nobody has to ask questions about.

## Proposing a change

Describe the **problem** first: what you were doing, what the board made you do instead, how often. Then
propose a solution, and say what you already ruled out — an existing setting, a theme, a host-side script.

Two constraints decide many proposals before the code is written:

- **The agent contract stays vendor-neutral.** The board talks to any CLI agent through `griglia:check` and
  `griglia:watch`. Nothing may hardcode an agent, a model or a provider: user-visible strings use the
  `:agent` placeholder, never a product name.
- **AI features are optional and off by default.** The package does not call a model provider on its own.

The **Idea or feature request** form makes you name the area your change belongs to and confirm those two
constraints before you send it: they are the questions that would otherwise come back to you a week later.

If your change adds a setting, say why the existing ones cannot express it: the settings page is a surface
that has to be documented, translated and kept working forever.

## Setting up

You need PHP 8.3+ and Composer; Node 22 only if you touch the front-end assets.

```bash
git clone https://github.com/alle80/griglia.git
cd griglia
composer install
composer qa            # lint + test + docs:check — everything a pull request must pass
```

`vendor/bin/testbench serve` gives you a bare Laravel application with the package mounted, which is the
fastest way to look at what you changed. The [development guide](development.md) has the repository map, the
Testbench and MySQL recipes, the factories and — read this one before running anything — **why the suite must
never be pointed at a real database**.

## What a change must carry

The whole bar in one page, with what each check protects and what to do when one fails, is
[Quality standards](quality.md). What follows is the short version.

### Code

- **Style is not a discussion**: `composer lint` runs Pint and PHPStan (Larastan level 5, no baseline). Both
  must be clean. `vendor/bin/pint` fixes the formatting for you.
- **Follow the surrounding code.** Laravel conventions, small classes, no framework of your own.
- **No new dependency** without a reason written in the pull request. A few lines beat a package.
- **Do not rename the historical names.** Sub-tasks are `Ingredient` in the code and in the database; lists
  are `Checklist`. They are documented in the [glossary](../glossary.md) — renaming them is a migration, not
  a clean-up.
- **Migrations are additive.** People run this package on a database with their tasks in it: add columns with
  a default, never rewrite an existing migration that has shipped.
- **UI follows the board's own style**: the icon set (`<x-griglia::icon name="…">`), the theme skin variables
  and the existing components — not emoji for states or actions, not one-off markup.

### Tests

Every behaviour you add or fix gets a test in `tests/Feature`, next to the ones for the same area
(`GrigliaCheckCommandTest`, `ReviewWorkflowTest`, `ThemesTest`…). The rule that matters: **the test must fail
without your change** — check it by stashing the fix and running the test again. Models have factories
(`Todo`, `Checklist`, `Ingredient`, `Question`), so a fixture is three lines.

### Translations

The base language is English (`resources/lang/en`), and `resources/lang/it` must stay in sync — a test fails
when a key exists in one and not in the other. Same rule for the documentation: every `page.md` has a
`page.it.md`, and a test rejects an Italian page that is a copy of the English one. If you do not speak
Italian, say so in the pull request and leave the translation to the maintainer; do not machine-translate a
page you cannot read.

### Documentation

Documentation changes in the **same commit** as the code. A user-visible change updates its page under
`docs/` in both languages; a new route, command or installation step also updates the `README.md`.

The three reference pages (`docs/reference/commands.md`, `config.md`, `settings.md`) are **generated** — edit
the command, the config file or the settings class, then run:

```bash
vendor/bin/testbench griglia:docs-generate        # regenerate the reference pages
vendor/bin/testbench griglia:docs-build --strict  # build the bilingual site, warnings are errors
```

CI runs `griglia:docs-generate --check` and fails if the generated pages are stale.

### CHANGELOG

One entry under **Unreleased** in [`CHANGELOG.md`](https://github.com/alle80/griglia/blob/master/CHANGELOG.md),
in the [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format (*Added*, *Changed*, *Fixed*,
*Removed*, *Security*, *Documentation*). Write it for the person upgrading: what changed for them, and the
migration step if there is one.

## Commits, branches and pull requests

- **Branch from an up-to-date `master`**, one logical change per branch. Never stack your branch on another
  contributor's branch.
- **Commit messages in English**, with a conventional prefix — `feat:`, `fix:`, `docs:`, `refactor:`,
  `test:`, `chore:` — and a subject that says the effect, not the file.
- **Keep the branch mergeable**: merge `master` back in when it moves. Force-pushing is fine before the first
  review, and after it prefer new commits so the review comments keep their context.
- **Open the pull request** with: the problem, what you changed, how you tested it, and `Closes #123` when an
  issue exists. Screenshots (before/after) for anything visual.

Checklist before you press the button — it is the same one the pull request template gives you to tick:

- [ ] `composer lint` is clean
- [ ] `composer test` is green
- [ ] `vendor/bin/testbench griglia:docs-build --strict` builds
- [ ] a test that fails without the change
- [ ] documentation updated, English **and** Italian
- [ ] a `CHANGELOG.md` entry under *Unreleased*
- [ ] no hardcoded agent name, no new dependency without a reason

## What happens next

CI runs the suite on the PHP 8.3/8.4 × Laravel 12/13 matrix, plus separate jobs for the lowest supported
dependency versions, MySQL 8, `composer audit`, Pint/PHPStan and the strict documentation build. A red matrix
is yours to fix; a red job you cannot reproduce is worth a comment — sometimes it is the project's fault.

Then a human reads it. The first review arrives within about two weeks
([response times](governance.md#response-times)), and it is a conversation: expect questions about scope and
naming before anything else. Silence past those times is not a rejection — ping the pull request.

Accepted work is merged by the maintainer, usually squashed, with your authorship intact. It ships in the
next release: a `vX.Y.Z` tag is what Packagist publishes, the *Unreleased* entry moves under that version,
and the documentation site is rebuilt from `master`. Releases are cut when there is something worth
releasing, not on a calendar — [Versioning and releases](releases.md) has the whole policy, including what a
minor bump is allowed to break.

If the change is refused, the reason is written in the pull request. Scope refusals often come with a better
home for the work: an extension point, a host-side script, or your own package.

## Contributions written with a coding agent

Griglia exists to make agent-assisted work observable, so pull requests written with an agent are welcome and
need no disclaimer. One rule: **the person who opens the pull request answers for it.** Read the diff, make
sure the tests fail without the change, and never paste generated documentation you have not checked against
the code. Agent-written prose that describes a behaviour the code does not have is worse than a missing page.

## How we talk to each other

Reviews are about the code, and they are direct: expect "this belongs in the host app" rather than a paragraph
of padding. Everyone gets the same courtesy in return — no contempt, no snark at somebody's setup or
language, no relitigating a decision that already has a written reason. The maintainer moderates and, if it
comes to that, closes threads that stop being technical.

Written down, that is the [Contributor Covenant 2.1](code-of-conduct.md), which this project adopts as it is:
what it covers, what it does not, and how a report is handled are on the [code of conduct](code-of-conduct.md)
page.

## License

Griglia is MIT-licensed and **what you contribute is MIT too**: no contributor licence agreement, no copyright
assignment, no sign-off. Opening a pull request is your agreement to publish that work under the terms in
[`LICENSE`](https://github.com/alle80/griglia/blob/master/LICENSE). If the change carries third-party code,
declare its origin and its license in the pull request — see [License](license.md).

## Security

Please do not open a public issue for a vulnerability — see [Security](../operations/security.md).
