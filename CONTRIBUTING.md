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
composer lint    # Laravel Pint + Larastan level 5
composer test    # phpunit through orchestra/testbench, SQLite in memory
```

Never point the test suite at a database that holds real data — the [development
guide](docs/contributing/development.md) explains the guard that stops it.

## The short version

- **Open an issue first** for anything larger than a fix, and check the scope table in
  [GOVERNANCE.md](GOVERNANCE.md): what belongs to the host application does not belong here.
- **A bug report** carries versions (Griglia, Laravel, PHP), the mode (`server` or `local`), what you did,
  expected and got, and the error or command output.
- **A change carries** a test that fails without it, documentation in English *and* Italian, a `CHANGELOG.md`
  entry under *Unreleased*, and a green `composer lint` / `composer test` /
  `vendor/bin/testbench griglia:docs-build --strict`.
- **Nothing hardcodes an agent**: user-visible strings use the `:agent` placeholder, never a product name.
- **Commits in English** with a conventional prefix (`feat:`, `fix:`, `docs:`…), one logical change per
  branch, branched from an up-to-date `master`.
- **Written with a coding agent?** Welcome, and no disclaimer needed — but the person who opens the pull
  request answers for it: read the diff.
- **License**: MIT, and what you contribute is MIT too — no agreement to sign. See [LICENSE](LICENSE) and the
  [license page](docs/contributing/license.md).
- **Security**: never a public issue — see [SECURITY.md](SECURITY.md).
