# Install Griglia in a Laravel application

This tutorial adds Griglia to an existing Laravel application and ends with an authenticated user opening a
working board. Allow about ten minutes for the required path; optional integrations can be added afterwards.

## Before you start

- PHP 8.3+, Laravel 12 or 13, Livewire 4.4+, Composer and a configured database
- `ext-gd`, `ext-fileinfo` and `ext-zip`
- a working authentication flow and at least one user for the default `server` mode
- the host Laravel application root as the current directory

Back up an existing application before changing dependencies or applying migrations.

## 1. Install the package

```bash
composer require alle80/griglia -W
```

`-W` lets Composer update transitive dependencies required by Web Push. Composer also publishes Griglia's
precompiled assets through Laravel's `laravel-assets` tag. A successful run adds `alle80/griglia` to
`composer.json` and completes without a dependency conflict.

## 2. Create the tables and settings

```bash
php artisan migrate
```

The migrations are idempotent and create board data, settings, notifications and push-subscription tables when
they do not already exist.

## 3. Open the board

Sign in to the host application and open `/`. Griglia should display a first list. Routes are registered behind
the `web` middleware and Griglia's access middleware; in `server` mode an unauthenticated request redirects to
the host login route.

If `/` belongs to the host application, publish `griglia.php`, disable `home_route`, and use the configured
dashboard route instead. See [access and modes](../configuration/access.md) for gates, administrators and local
mode.

## 4. Connect an agent

```bash
php artisan vendor:publish --tag=griglia-agents
```

This writes the portable `AGENTS.md` workflow to the project root. Create or rename a list to match
`GRIGLIA_AGENT_LIST` (`dev` by default), start the coding agent in that directory, then run:

```bash
php artisan griglia:check
```

Expected result: the command prints behaviour settings and the open/working items for the agent list.

## Verify the installation

```bash
php artisan route:list --name=griglia
php artisan griglia:check --all
```

Confirm that Griglia routes are present, the board opens for the intended user, and the CLI can read the same
list. Complete the [quickstart](quickstart.md) to exercise the full request lifecycle.

## Optional integrations

- [Front-end assets](assets.md): switch from precompiled files to the host Vite build.
- [Live updates and notifications](../features/notifications.md): configure a broadcaster and Web Push.
- [AI features](../features/ai.md): enable plan generation, transcription and image descriptions.
- [Themes](../features/themes.md): select or install a visual theme.

### Live updates (optional)

Configure a broadcaster only after the required installation works. The canonical setup and verification are
in [notifications](../features/notifications.md).

### Web Push (optional)

Web Push requires HTTPS, VAPID keys and a user model with the subscription trait. Follow the same
[notifications guide](../features/notifications.md) instead of duplicating the procedure here.

## Common problems

| Symptom | Likely cause | Action |
|---|---|---|
| Composer reports a `brick/math` conflict | transitive packages are locked | repeat the require command with `-W` |
| `/` redirects to login | normal `server`-mode protection | sign in or configure access deliberately |
| `/` is 404 after installation | stale route cache | run `php artisan route:cache` or clear the cache during setup |
| CSS or JavaScript is missing | assets were not published or mode is inconsistent | republish `laravel-assets` or follow the Vite guide |
| The agent list is empty | its name differs from `GRIGLIA_AGENT_LIST` | rename the list or update the configuration |
