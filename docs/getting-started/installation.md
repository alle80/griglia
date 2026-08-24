# Install Griglia in a Laravel application

This tutorial adds Griglia to an existing Laravel application and ends with an authenticated user opening a
working board, an agent reading its instructions and `griglia:check` listing the queue. Allow about ten
minutes; optional integrations can be added afterwards.

## The short version

From the root of the host Laravel application:

```bash
composer require alle80/griglia -W
php artisan vendor:publish --tag=griglia-config     # config/griglia.php — decide the keys below
php artisan migrate                                 # after choosing GRIGLIA_TABLE_PREFIX
php artisan vendor:publish --tag=griglia-agents     # AGENTS.md, the workflow the agent reads
php artisan vendor:publish --tag=griglia-scripts    # scripts/ on the host (context, tokens, skills)
php artisan griglia:check                           # the agent's view of the board
```

Then sign in and open `/`. Each step is explained below — read them if anything in that block is not obvious,
and mind the [backup warning](#5-the-files-the-agent-reads) before the last two commands.

## Before you start

- PHP 8.3+, Laravel 12 or 13, Livewire 4.4+, Composer and a configured database
- `ext-gd`, `ext-fileinfo` and `ext-zip`
- a working authentication flow and at least one user for the default `server` mode
- `python3` on the machine where the agent runs, for the host scripts
- the host Laravel application root as the current directory

Back up an existing application before changing dependencies or applying migrations.

## 1. Install the package

```bash
composer require alle80/griglia -W
```

`-W` lets Composer update transitive dependencies required by Web Push. Composer also publishes Griglia's
precompiled assets through Laravel's `laravel-assets` tag. A successful run adds `alle80/griglia` to
`composer.json` and completes without a dependency conflict.

## 2. Publish the configuration

```bash
php artisan vendor:publish --tag=griglia-config
```

This writes `config/griglia.php`, a commented file where every key reads an environment variable. You can skip
it and set only the variables in `.env` — but publish it if you need the keys that have no variable
(`middleware`, `themes`, `register_routes`, `home_route`, `push_allowed_hosts`).

### The keys that matter on day one

| Key | Variable | Default | Set it when |
|---|---|---|---|
| `table_prefix` | `GRIGLIA_TABLE_PREFIX` | `griglia_` | you want the board tables named differently — **decide before the first `migrate`** |
| `user_model` | `GRIGLIA_USER_MODEL` | `App\Models\User` | your user model lives elsewhere |
| `mode` | `GRIGLIA_MODE` | `server` | the board runs on your own machine: `local` drops authentication and makes lists global |
| `route_prefix` | `GRIGLIA_ROUTE_PREFIX` | `''` (site root) | `/` belongs to your application: `board` serves `/board`, `/board/settings`, … |
| `dashboard_route` | `GRIGLIA_DASHBOARD_ROUTE` | `/dashboard` | you want the board on a single path, or none at all (`null`) |
| `agent_list` | `GRIGLIA_AGENT_LIST` | `dev` | the list you queue work in has another name |
| `agent_name` | `GRIGLIA_AGENT_NAME` | `Agent` | the UI should say «Claude», «Codex», … |
| `agents` / `agent_key` | `GRIGLIA_AGENTS`, `GRIGLIA_AGENT_KEY` | one agent | two or more CLI agents share the board |
| `attachments_disk` | `GRIGLIA_ATTACHMENTS_DISK` | `local` | image attachments belong on another disk |

A working `.env` for the common case — the board at the site root, one Claude Code agent, a list named `dev`:

```dotenv
GRIGLIA_MODE=server
GRIGLIA_AGENT_LIST=dev
GRIGLIA_AGENT_NAME=Claude
```

And for a board on your own machine, with two agents on it:

```dotenv
GRIGLIA_MODE=local
GRIGLIA_AGENTS="claude:Claude Code,codex:Codex CLI"
GRIGLIA_AGENT_KEY=claude
```

`GRIGLIA_MODE=local` removes authentication: use it only on a trusted machine bound to `127.0.0.1`.

!!! note "Cached configuration"
    An application that runs `config:cache` (any production deploy) does not see a changed `.env` until you run
    `php artisan config:cache` again — and `php artisan route:cache` after changing `route_prefix`,
    `home_route` or `dashboard_route`. A 404 on a route you just enabled is almost always a stale cache.

Everything else lives in the generated [configuration reference](../reference/config.md); who may open and
administer the board is in [access and modes](../configuration/access.md).

## 3. Create the tables and settings

```bash
php artisan migrate
```

The migrations are idempotent and create board data, settings, notifications and push-subscription tables when
they do not already exist. They honour `table_prefix`, so a board installed on the default `griglia_` prefix
keeps every table of the package together. Changing the prefix later means renaming those tables yourself.

## 4. Open the board

Sign in to the host application and open `/`. Griglia should display a first list. Routes are registered behind
the `web` middleware and Griglia's access middleware; in `server` mode an unauthenticated request redirects to
the host login route — an application with no authentication at all answers `Route [login] not defined`, so
add a login flow first or, on a trusted machine, use `GRIGLIA_MODE=local`.

If `/` belongs to the host application, set `route_prefix` or disable `home_route` and use the configured
dashboard route instead.

## 5. The files the agent reads

A CLI agent reads one Markdown file at the project root at every turn: `AGENTS.md` (Codex CLI, Cursor, Amp,
Zed…), `CLAUDE.md` (Claude Code), `GEMINI.md` (Gemini CLI). Griglia can **generate** those files from the
context you manage on `/context`, so the rules the agent follows are switchable blocks instead of a file
nobody dares to touch.

!!! warning "Back up your instruction files before this step"
    Generation **overwrites** `CLAUDE.md` and `AGENTS.md` at the project root with the content of the board. If
    you already have hand-written ones, save them first — a single command, and you can undo everything later:

    ```bash
    php artisan vendor:publish --tag=griglia-scripts   # puts the host scripts in scripts/
    python3 scripts/sync-context.py --backup                   # copies them to docs/context-originals/
    cp AGENTS.md CLAUDE.md ~/backup/                   # belt and braces; committing them to git also works
    ```

    `--backup` saves a file only if it is not generated and not saved already, so run it **before** the first
    sync. To undo: `python3 scripts/sync-context.py --restore` puts the originals back, and switching off «Generate
    instruction files from the board» on `/context` restores them and stops the generation for good.
    `vendor:publish` never overwrites an existing file unless you add `--force` — which is exactly the flag
    that would eat an `AGENTS.md` of your own.

### Start from the packaged workflow

If you have no instructions file yet, publish the one shipped with Griglia:

```bash
php artisan vendor:publish --tag=griglia-agents
```

This writes the portable `AGENTS.md` — board states, the `griglia:check` lifecycle, the rules an agent must
follow — to the project root.

### Or start from the file you already have

```bash
php artisan griglia:context import --file=CLAUDE.md
```

The Markdown becomes groups (one per `##` heading) and blocks on `/context`, each with its own switch and a
token estimate. Add `--replace` to wipe the current context and import again from scratch.

### Generate the files

The host script turns the enabled blocks back into the instruction files:

```bash
python3 scripts/sync-context.py            # write CLAUDE.md and AGENTS.md if the content changed
python3 scripts/sync-context.py --check    # exit 1 if they are out of date (useful in CI)
```

A generated file opens with `<!-- Generated by Griglia (/context) … -->`: edit the blocks on the page, not the
file. Keep it up to date without thinking about it:

```cron
* * * * * cd /srv/app && python3 scripts/sync-context.py -q
```

`GRIGLIA_CONTEXT_TARGETS="CLAUDE.md,AGENTS.md,GEMINI.md"` adds a target file. The host scripts reach Artisan
through `docker exec` by default; when Laravel runs directly on the machine, set `GRIGLIA_TRANSPORT=local` —
see [host scripts](../agent/scripts.md) and [agent context](../agent/context.md).

## 6. Start the agent

Create or rename a list to match `GRIGLIA_AGENT_LIST` (`dev` by default), add a request to it and click its
state control once, so the task is **open to work**.

Then open a terminal in the project root — the directory with `artisan` and the instruction file — and start
your agent CLI **there**:

```bash
cd /srv/my-project
claude            # Claude Code · `codex` for Codex CLI · `gemini` for Gemini CLI
```

Give it the first message:

```{ .text .agent-prompt title="First message — copy it" }
Read AGENTS.md and work on the Griglia board as agent claude: run php artisan griglia:check --agent=claude, take the first task that is open to work, and follow the workflow through to closing it.
```

Expected result: `griglia:check` prints the behaviour settings and the queue of the agent list, and the task dot
turns to **working** on the board. You can run the same command yourself at any time to see what the agent sees.

That interactive session is the first of three ways to run an agent — the other two are a single
non-interactive command and a service that starts sessions by itself. All three are in [start the
agent](../agent/running.md).

## Verify the installation

```bash
php artisan route:list --name=griglia
php artisan griglia:check --all
python3 scripts/sync-context.py --check
```

Confirm that Griglia routes are present, the board opens for the intended user, the CLI can read the same list
and the instruction files match the board. Complete the [quickstart](quickstart.md) to exercise the full
request lifecycle.

## Optional integrations

- [Front-end assets](assets.md): switch from precompiled files to the host Vite build.
- [Live updates and notifications](../features/notifications.md): configure a broadcaster and Web Push.
- [AI features](../features/ai.md): enable plan generation, transcription and image descriptions.
- [Themes](../features/themes.md): select or install a visual theme.

Live updates and Web Push are worth configuring only after the required installation works: the canonical
setup, including HTTPS, VAPID keys and the subscription trait on the user model, is in
[notifications](../features/notifications.md).

## Common problems

| Symptom | Likely cause | Action |
|---|---|---|
| Composer reports a `brick/math` conflict | transitive packages are locked | repeat the require command with `-W` |
| `/` redirects to login | normal `server`-mode protection | sign in or configure access deliberately |
| `/` answers 500 with `Route [login] not defined` | the host application has no authentication at all | add a starter kit or a route named `login`, or use `GRIGLIA_MODE=local` on a trusted machine |
| `/` is 404 after installation | stale route cache | run `php artisan route:cache` or clear the cache during setup |
| A changed `.env` has no effect | cached configuration | run `php artisan config:cache` |
| CSS or JavaScript is missing | assets were not published or mode is inconsistent | republish `laravel-assets` or follow the Vite guide |
| The agent list is empty | its name differs from `GRIGLIA_AGENT_LIST` | rename the list or update the configuration |
| `CLAUDE.md` came back different | the board generates it | edit the blocks on `/context`, or `python3 scripts/sync-context.py --restore` |
| The board tables are missing after an upgrade | the prefix changed | keep `GRIGLIA_TABLE_PREFIX` as it was, or rename the tables |
