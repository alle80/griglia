# FAQ

Short answers to the questions that come up before installing Griglia. The long versions live in the pages
each answer links to.

## Does Griglia work with my coding agent?

Griglia does not integrate with a specific agent: the contract is two Artisan commands and one instructions
file. Any CLI agent that can run `php artisan griglia:check` and read a Markdown file can use the board —
Claude Code, Codex CLI, Gemini CLI and others have been used this way. `vendor:publish --tag=griglia-agents`
writes the instructions the agent reads. See [the agent side](agent/index.md).

## Is it a separate application?

No. Griglia is a Composer package that installs into an existing Laravel application: `composer require`,
`php artisan migrate`, and the board is served by your app, with your users and your database. See the
[installation tutorial](getting-started/installation.md).

## Do I need an AI key or an internet connection?

No. The board, the agent workflow, notifications and themes work with no AI provider at all. Image
descriptions, plans built from a prompt and voice transcription are optional and only run when `laravel/ai`
is configured. See [AI features](features/ai.md).

## Do I need Node, Vite or Tailwind?

Not in the default setup: Griglia ships precompiled CSS and JavaScript. If you prefer to compile the package
with your own Tailwind build, set `GRIGLIA_ASSETS=vite` and follow [front-end assets](getting-started/assets.md).

## Can more than one agent work at the same time?

Yes. Each list and each task can be assigned to an agent, `griglia:check --agent=<key>` shows only that
agent's work, and a task claimed elsewhere is reported as busy. See [two agents at once](agent/concurrency.md).

## Can I run it without logging in?

`GRIGLIA_MODE=local` removes authentication and makes the lists global — for a board on your own machine,
bound to `127.0.0.1`. Never expose local mode to a network. On a server, access is controlled by
`canAccessGriglia()` or `GRIGLIA_ACCESS_GATE`, and the administrative pages by `canManageGriglia()`,
`GRIGLIA_ADMIN_GATE` or `GRIGLIA_ADMINS`. See [access, administrators and modes](configuration/access.md).

## Where does my data live?

In your application's database and storage: tasks, notes, attachments, questions, results, working time and
token counts. Griglia sends nothing anywhere unless you configure notifications or an AI provider yourself.

## Is it stable enough to use?

It is used daily by its author, has a test suite running on every push, and follows semantic versioning —
but it is still `0.x`: minor versions may change behaviour, and the changelog says when. See the
[upgrade runbook](operations/upgrading.md) and the [governance and support policy](contributing/governance.md).
