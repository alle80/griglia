# Run Griglia without Docker

Griglia is a Laravel package: whatever serves your application — `composer dev`, `php artisan serve`, Apache,
nginx with PHP-FPM, a container — serves the board too. Only the **host scripts** ever needed to know where
Artisan lives, and since v0.94.0 they find out by themselves.

This page is for a machine where PHP runs directly on the host, and lists what changes compared with a
containerized setup.

## The Artisan transport

`sync-context.py`, `sync-skills.py`, `claude-tokens.py`, `agent-status.py` and the persistent worker reach the
board through one **transport**:

| `GRIGLIA_TRANSPORT` | What runs | When to pick it |
| --- | --- | --- |
| `auto` (default) | `docker exec` if `$GRIGLIA_CONTAINER` is running, `php artisan` otherwise | you want it to just work |
| `docker` | `docker exec <container> php artisan` | the application lives in a container |
| `local` | `php artisan` from the project root | PHP runs on this machine |

With `auto` each run asks Docker once whether the container is up; a missing `docker` binary, a stopped daemon
or a stopped container all mean the same thing — run `php artisan` here, in the project root. Nothing to
configure on a machine that has never seen Docker.

Pin the transport where the probe is pure overhead (cron, systemd) or where both worlds exist on the same
host and you want to be sure which one answers:

```dotenv
GRIGLIA_TRANSPORT=local
GRIGLIA_PHP=/usr/bin/php8.4        # when `php` is not the right binary
GRIGLIA_PROJECT_ROOT=/srv/my-app   # when the scripts are not under <project>/scripts
```

When Artisan cannot be reached the scripts print the transport they used and the variable that changes it:

```text
Cannot connect to the Docker daemon at unix:///var/run/docker.sock. Is the docker daemon running?
artisan ran through `docker exec laravel-dev-app`: set GRIGLIA_CONTAINER, or GRIGLIA_TRANSPORT=local to use PHP on this machine
```

The [persistent worker](../agent/workers.md) reads the same variables and accepts `--transport auto|docker|local`
per instance; it prints the resolved transport at startup.

## Cron and systemd start from an empty environment

The scripts are meant to run unattended, and neither cron nor systemd inherits the shell where you exported
your variables. Give both absolute paths and the few variables they need:

```cron
* * * * * cd /srv/my-app && GRIGLIA_TRANSPORT=local /usr/bin/python3 scripts/sync-context.py -q
*/5 * * * * cd /srv/my-app && GRIGLIA_TRANSPORT=local /usr/bin/python3 scripts/agent-status.py -q
```

For the worker unit, the same variables belong in its `EnvironmentFile`
(`~/.config/griglia-worker/<agent-key>.env`).

## What else changes

**File ownership.** The scripts run Artisan as *your* user and write inside `storage/app/griglia`
(`skills.json`, `agent-status.json`, the last-check marker); the web process writes the same files as its own
user. With Docker `-u www-data` hid the problem. Here, either run the scripts as the web user
(`sudo -u www-data python3 scripts/sync-skills.py`) or put both users in one group and keep the directory
group-writable:

```bash
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R g+rwXs storage bootstrap/cache
```

**The scheduler.** `griglia:auto-archive` (daily at 03:30) only runs if Laravel's scheduler runs — one cron
entry, or `php artisan schedule:work` in development:

```cron
* * * * * cd /srv/my-app && php artisan schedule:run >> /dev/null 2>&1
```

**Live updates.** Broadcasting stays optional: with no broadcaster the board works and just does not refresh
by itself. Where you do run Reverb, restart it after upgrading the package (`php artisan reverb:restart`) —
the worker process holds the old code otherwise.

**Caches.** A container that re-runs `config:cache` at every start forgives a stale cache; a bare host does
not. After editing `.env` or `config/griglia.php` run `php artisan config:cache`, and `php artisan route:cache`
after changing `route_prefix` — a `404` on a Griglia route is almost always this.

**Assets.** Publishing the compiled CSS/JS is the same as anywhere else, and must be repeated on upgrade:
`php artisan vendor:publish --tag=griglia-assets --force` — see [Front-end assets](assets.md).

**Notifications** are sent inline, without `ShouldQueue`: a queue worker is not required. Web Push still needs
`/griglia-sw.js` served from the site root, which the package route does for you as long as the document root
is `public/`.

**The instruction file.** `AGENTS.md` tells the agent how to call Artisan. On a host without containers the
commands are plain `php artisan griglia:check` — check the wording after publishing it, see
[Start the agent](../agent/running.md).
