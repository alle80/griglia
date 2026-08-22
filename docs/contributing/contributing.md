# Contributing

Issues and pull requests are welcome at
[github.com/alle80/griglia](https://github.com/alle80/griglia). [Governance](governance.md) says who decides,
what is in scope, which versions are supported and how long an answer takes.

## Before opening a pull request

```bash
cd packages/griglia && composer update
composer lint
composer test
```

The suite (orchestra/testbench, in-memory SQLite) covers migrations, per-user scoping, the Livewire
components, `griglia:check` / `griglia:watch`, the theme registry and zip packs, translation parity and the
broadcast event. GitHub Actions runs it on PHP 8.3 and 8.4.

## What a change should carry

- **Tests** for the behaviour you add or fix.
- **Translations**: strings live in `resources/lang/en` (base) and `resources/lang/it`; a test checks that
  the two files stay in sync. Never hardcode the agent's name — use `:agent`.
- **Docs**: if the change is visible to users, update the matching page in `docs/`.
- **CHANGELOG.md**: one entry under *Unreleased*, in the Keep a Changelog format.

## Style

Run `composer lint` to check the Laravel Pint style and `composer test` to run PHPUnit. Follow the surrounding
code: Laravel conventions, no new dependency without a reason, UI built with the
package's icon set and theme variables rather than one-off markup.

## License

Griglia is MIT-licensed and **what you contribute is MIT too**: no contributor licence agreement, no copyright
assignment, no sign-off. Opening a pull request is your agreement to publish that work under the terms in
[`LICENSE`](https://github.com/alle80/griglia/blob/master/LICENSE). If the change carries third-party code,
declare its origin and its license in the pull request — see [License](license.md).

## Security

Please do not open a public issue for a vulnerability — see [Security](../operations/security.md).
