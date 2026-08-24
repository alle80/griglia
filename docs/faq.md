# FAQ

Short answers to the questions that come up most often: before installing Griglia, and in the first days of
using the board. The long versions live in the pages each answer links to. If a word here is unfamiliar, the
[glossary](glossary.md) defines it.

## Before you install

### Does Griglia work with my coding agent?

Griglia does not integrate with a specific agent: the contract is two Artisan commands and one instructions
file. Any CLI agent that can run `php artisan griglia:check` and read a Markdown file can use the board —
Claude Code, Codex CLI, Gemini CLI and others have been used this way. `vendor:publish --tag=griglia-agents`
writes the instructions the agent reads. See [the agent side](agent/index.md).

### Is it a separate application?

No. Griglia is a Composer package that installs into an existing Laravel application: `composer require`,
`php artisan migrate`, and the board is served by your app, with your users and your database. See the
[installation tutorial](getting-started/installation.md).

### Do I need an AI key or an internet connection?

No. The board, the agent workflow, notifications and themes work with no AI provider at all. Image
descriptions, plans built from a prompt and voice transcription are optional and only run when `laravel/ai`
is configured. See [AI features](features/ai.md).

### Do I need Node, Vite or Tailwind?

Not in the default setup: Griglia ships precompiled CSS and JavaScript. If you prefer to compile the package
with your own Tailwind build, set `GRIGLIA_ASSETS=vite` and follow [front-end assets](getting-started/assets.md).

### Is it stable enough to use?

It is used daily by its author, has a test suite running on every push, and follows semantic versioning —
but it is still `0.x`: minor versions may change behaviour, and the changelog says when. See the
[upgrade runbook](operations/upgrading.md) and the [governance and support policy](contributing/governance.md).

## Using the board

### I added a task, but `griglia:check` says there is nothing to do

Two usual reasons, both by design:

- **The task is still *waiting***. The agent only sees what you have released: tap the state dot once, so it
  becomes *open to work*. Everything else on the board is yours to edit while the agent stays out of it.
- **The task is still *waiting* in another list**. Only the **agent list** — `agent_list` in the
  configuration, `dev` by default, changeable with `GRIGLIA_AGENT_LIST` or in Settings — is the channel the
  agent reads top to bottom. In any other list of yours nothing happens until you release a task: mark it
  *open to work* and `griglia:check` shows it under `📋 List «…»`, after the agent list and after the plans.
  For everyday requests, keep using the agent list: that is where priority is set by drag and drop.

Started plans work the same way: their tasks show up after the agent list. See
[using the board](board/usage.md) and [plans](features/plans.md).

### Can I run it without logging in?

`GRIGLIA_MODE=local` removes authentication and makes the lists global — for a board on your own machine,
bound to `127.0.0.1`. Never expose local mode to a network. On a server, access is controlled by
`canAccessGriglia()` or `GRIGLIA_ACCESS_GATE`, and the administrative pages by `canManageGriglia()`,
`GRIGLIA_ADMIN_GATE` or `GRIGLIA_ADMINS`. See [access, administrators and modes](configuration/access.md).

### Where does my data live?

In your application's database and storage: tasks, notes, attachments, questions, results, working time and
token counts. Griglia sends nothing anywhere unless you configure notifications or an AI provider yourself.

## Working with agents

### Can more than one agent work at the same time?

Yes. Each list and each task can be assigned to an agent, `griglia:check --agent=<key>` shows only that
agent's work, and a task claimed elsewhere is reported as busy. See [two agents at once](agent/concurrency.md).

### Can the agent keep working while I am away?

Yes, with a **persistent worker**: a small host process, published by `vendor:publish --tag=griglia-scripts`
and usually run as a systemd user service, that polls the board and starts a fresh non-interactive agent
session whenever a task is open to work. One instance per agent key, its own lock, and stop requests are
honoured. See [persistent workers](agent/workers.md).

### How do I find out that the agent asked something or finished?

The board tells you by itself: in-app bell, Web Push on the devices you enable, and mail when a mailer is
configured — with a deep link that opens the task. The two events follow the `notify_on_done` and
`notify_on_question` settings, which silence both the board and the agent's own channel. See
[notifications](features/notifications.md).

### What does it cost in tokens, and how do I spend less?

Griglia does not call any model on your behalf — the tokens are your agent's. It records what the agent
reports when it closes a task and turns it into a cost on `/stats`, using the price per million tokens you
set in Settings → App. To spend less, the ⚡ *Optimization* settings tune the agent's own habits: compact
command output, terse mode, percentage updates only piggybacked on other commands. See
[statistics](agent/stats.md) and the [settings reference](reference/settings.md).
