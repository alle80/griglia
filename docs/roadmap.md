# Roadmap

What Griglia already does, what is being built next, and what it will never do. Written so you can tell in
a minute whether the package fits — without opening the issue tracker.

**No dates.** The order below is a preference, not a schedule: the list is worked top to bottom, but a
release happens when a piece is finished, not on a calendar. Versions are `0.x`: see
[versioning and releases](contributing/releases.md) for what that promises.

## Where it is today

| Area | State |
|---|---|
| The task cycle | Complete: waiting → open → working → done, with questions, pause, stop and resume — see [architecture](architecture.md) |
| The agent contract | `griglia:check` (take, progress, phase, ask, done, outcome, tokens) and `griglia:watch`, with several agents and per-task ownership |
| Unattended work | [Persistent workers](agent/workers.md) with Codex, Claude Code or a custom driver |
| Plans | A goal becomes a chain of tasks; closing one opens the next |
| Notifications | In-app bell, Web Push and mail, each switchable |
| Look and feel | Ten styles, installable [theme packs](features/themes.md), English and Italian, a third language by publishing translations |
| Optional AI | Plans from a prompt, image descriptions, speech-to-text |
| Documentation | This bilingual site, with the command, config and settings references generated from the code |

## What comes next

Each line is a self-contained piece of work with its decision already taken. They can land in any order,
and each one ships in its own release.

| Next | What it adds |
|---|---|
| **Quality gate** | PHPStan level 5 with no baseline, Pint, a CI matrix over PHP 8.3/8.4 × Laravel 12/13, `--prefer-lowest`, a MySQL job, `composer audit`, model factories |
| **First contact** | Screenshots and a short recording in the README, and a quickstart verified against a fresh `laravel new` |
| **`griglia:install`** | One idempotent command: config, migrations, the agent list, `storage:link`, the instruction file only when absent, and `--user=` to create the first administrator |
| **Zero-config access** | `local` mode documented as the recommended path for personal use; `server` mode keeps using your own authentication — the package will not ship a login |
| **Task history** | A timeline of the state changes of a task, with the actor (you, an agent, the board), shown as an accordion in the modal |
| **Stuck tasks and `griglia:doctor`** | A task left *working* with no progress for too long is flagged and notified once — never reopened automatically — plus a health check for migrations, settings, disks, VAPID keys, broadcasting and the instruction file |
| **Export, import and a versioned `--json`** | `griglia:export` to a zip (JSON plus attachments), `griglia:import` creating new lists, Markdown export of a task or a plan, and one documented schema shared by the export and `--json` |
| **File and link attachments** | Beyond images: a safe allow-list of file types (no HTML, SVG or executables) and plain links shown as chips |
| **Keyboard shortcuts** | Search, new task, move and open, edit, toggle done, switch list, and a `?` overlay — no single key for a destructive action |
| **Towards 1.0** | A first install by somebody who did not write the package, then the 1.0 release once the schema and the command options are stable |

## Out of scope by choice

These are not "not yet": they are decisions. If one of them is what you need, Griglia is the wrong tool and
that is fine.

| Not planned | Why |
|---|---|
| Teams, sharing, assigning tasks to people | Griglia is single-owner: one person and their agents. Scoping stays "your lists only" |
| Labels, due dates, priorities, estimates, recurrences | The board is a queue: the order you drag rows into *is* the priority |
| HTTP API, machine tokens, webhooks, MCP server | The contract is Artisan plus a worker. Whatever runs the command already has a shell and the database — a second, weaker door would only widen the surface. To react to changes, listen to [`TodoChanged`](reference/events.md) in your own application |
| Telegram, Slack or other chat channels | Bell, Web Push and mail already reach you; every extra channel is an integration to keep alive |
| A PWA and offline reading | The board is Livewire: it needs the server. An offline copy would be a second, lying source of truth |
| Its own login or a public demo | Authentication belongs to your application ([access and modes](configuration/access.md)) |
| A trash bin, a light/dark toggle, locale-specific date formats | Small conveniences that cost more than they give: the archive, the themes and the ISO dates already cover them |

## How this is decided

The direction is set by the maintainer, in the open: see [governance](contributing/governance.md). A
proposal is welcome as an issue that says what you were trying to do and where the board got in the way —
[contributing](contributing/contributing.md) explains the rest. A feature that is out of scope above can
still be built on top: the seams are documented in [Extending Griglia](configuration/extending.md).

Already-released work is in the [changelog](reference/changelog.md).
