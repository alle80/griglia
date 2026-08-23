# Contributing to Griglia

Issues and pull requests are welcome at [github.com/alle80/griglia](https://github.com/alle80/griglia).
Griglia is maintained by one person on personal time: a short, complete contribution gets merged, a large one
that nobody discussed first usually waits.

The full guidelines — how to report a bug, how to propose a change, what a pull request must carry, what
happens after you open it — live on the documentation site and are not duplicated here:

**<https://alle80.github.io/griglia/contributing/contributing/>**
([source](docs/contributing/contributing.md), [italiano](docs/contributing/contributing.it.md))

## In one minute

```bash
git clone https://github.com/alle80/griglia.git
cd griglia
composer install
composer qa      # lint + test + docs:check: everything a pull request must pass
```

`composer qa` is Pint, PHPStan level 5, the PHPUnit suite through Testbench on in-memory SQLite, and the check
that the generated reference pages still match the code. The bar it enforces — and what is deliberately left
to review — is the [quality standards page](docs/contributing/quality.md).

Never point the test suite at a database that holds real data — the [development
guide](docs/contributing/development.md) explains the guard that stops it.

## The short version

- **Open an issue first** for anything larger than a fix, and check the scope table in
  [GOVERNANCE.md](GOVERNANCE.md): what belongs to the host application does not belong here.
- **Use the forms.** *New issue* offers a bug report, an idea, a documentation problem and a question
  ([templates](.github/ISSUE_TEMPLATE)); pull requests open with a
  [template](.github/PULL_REQUEST_TEMPLATE.md) that asks for the problem, the change, how you tested it and
  the checklist below. They ask for what a review needs anyway.
- **A bug report** carries versions (Griglia, Laravel, PHP), the mode (`server` or `local`), what you did,
  expected and got, and the error or command output.
- **A change carries** a test that fails without it, documentation in English *and* Italian, a `CHANGELOG.md`
  entry under *Unreleased*, and a green `composer lint` / `composer test` /
  `vendor/bin/testbench griglia:docs-build --strict`.
- **Nothing hardcodes an agent**: user-visible strings use the `:agent` placeholder, never a product name.
- **Commits in English** with a conventional prefix (`feat:`, `fix:`, `docs:`…), one logical change per
  branch, branched from an up-to-date `master`.
- **Versions**: the project is on `0.x`, where a **minor** may break and a **patch** never does, and only the
  newest minor is supported. The policy and the release procedure are on the
  [versioning and releases page](docs/contributing/releases.md).
- **Written with a coding agent?** Welcome, and no disclaimer needed — but the person who opens the pull
  request answers for it: read the diff.
- **Conduct**: the [Contributor Covenant 2.1](CODE_OF_CONDUCT.md) — reviews are direct about the code, never
  about the person. Reports go to the maintainer by e-mail.
- **License**: MIT, and what you contribute is MIT too — no agreement to sign. See [LICENSE](LICENSE) and the
  [license page](docs/contributing/license.md).
- **Security**: never a public issue — see [SECURITY.md](SECURITY.md).
