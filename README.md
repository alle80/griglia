<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="public/images/brand/lockup-horizontal-dark.svg">
    <img src="public/images/brand/lockup-horizontal.svg" width="380" alt="Griglia">
  </picture>
</p>

# alle80/griglia

Griglia is a Laravel board where people queue work and coding agents take it, report progress, ask questions
and return a verifiable result. It supports Laravel 12/13, Livewire 4, multiple users and lists, plans,
attachments, notifications, installable themes and more than one agent.

## Requirements

- PHP 8.3 or later, Laravel 12 or 13, and Livewire 4.4 or later
- `ext-gd`, `ext-fileinfo` and `ext-zip`
- an authenticated user in the default `server` mode
- Tailwind CSS 4 only when choosing the optional `vite` asset mode

Optional integrations include `laravel/ai` for image descriptions, plans and transcription, and a Laravel
broadcaster such as Reverb for live updates.

## Install

From the root of the host Laravel application:

```bash
composer require alle80/griglia -W
php artisan migrate
```

`-W` allows Composer to resolve the Web Push dependency tree in a fresh Laravel application. The default
precompiled asset mode requires no Node build. Open `/` while authenticated: the board should load and create
the first list for the user.

For access gates, local mode, optional integrations, asset alternatives and a complete verification, follow
the [installation tutorial](docs/getting-started/installation.md). Then complete the
[quickstart](docs/getting-started/quickstart.md).

## Connect a coding agent

Publish the portable instructions from the host application root:

```bash
php artisan vendor:publish --tag=griglia-agents
```

Create or rename a list to match `GRIGLIA_AGENT_LIST` (`dev` by default), start the agent in the project
directory, and inspect the queue:

```bash
php artisan griglia:check
```

The board workflow is `waiting -> open to work -> working -> done`. The agent's first action is
`griglia:check --take=ID`; it uses `--ask=ID` to pause for an answer and `--done=ID` to return its result.
`griglia:watch` reports new work, answers and stop requests for interactive sessions. For unattended operation,
use the [persistent worker runbook](docs/agent/workers.md).

## Routes and access

The package registers `/`, `/plans`, `/plans/new`, `/settings`, `/context`, `/stats`, `/agents` and
`/dashboard` (a redirect to the board, kept for old links). The dashboard path is configurable and can be
disabled; without a home route it serves the board itself. In `server` mode, lists belong to the
authenticated user; restrict access with `canAccessGriglia()` or `GRIGLIA_ACCESS_GATE`. Administrative pages
use `canManageGriglia()`, `GRIGLIA_ADMIN_GATE` or `GRIGLIA_ADMINS`.

`GRIGLIA_MODE=local` removes authentication and makes lists global. Use it only on a trusted machine bound to
`127.0.0.1`; never expose local mode to a network.

See [access and modes](docs/configuration/access.md) for the complete contract and the generated
[configuration](docs/reference/config.md) and [settings](docs/reference/settings.md) references for exact keys.

## Front-end assets

Precompiled assets are the default and are published through Laravel's `laravel-assets` tag. If the host app
must compile the package with Tailwind/Vite, publish the config, set `GRIGLIA_ASSETS=vite`, import the package
CSS and JavaScript, and include package Blade files in Tailwind's sources. The canonical commands and trade-offs
are in [front-end assets](docs/getting-started/assets.md).

## Development

```bash
composer install
composer lint
composer test
```

Tests use in-memory SQLite by default and include a guard against destructive execution on a live database.
Read the [development guide](docs/contributing/development.md) before running Testbench or MySQL tests.

## Documentation and support

- [Documentation site](https://alle80.github.io/griglia/)
- [Feature overview](docs/features/index.md)
- [Upgrade runbook](docs/operations/upgrading.md)
- [Troubleshooting](docs/operations/troubleshooting.md)
- [Security policy](SECURITY.md)
- [Changelog](CHANGELOG.md)

Griglia is released under the [MIT license](LICENSE.md).
