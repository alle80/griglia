# Glossary

The words used across this documentation, the interface and the Artisan commands. Where the code uses a
different name, it is noted — those names are historical and are not going to change.

## The board

**Board** — the page Griglia serves at `/`: one list at a time, its tasks, and the controls to filter,
search and switch list.

**List** — a named set of tasks belonging to a user (`Checklist` in the code). The current list is kept in
the session, so every query is scoped to one user and one list.

**Agent list** — the list a coding agent reads, `agent_list` in the configuration (`dev` by default). Other
lists are yours; the agent ignores them.

**Task** — one row of the board (`Todo` in the code): a title, an optional note, sub-tasks, images, a state
and, once it is done, the agent's result.

**Sub-task** — a checklist item inside a task (`Ingredient` in the code and in the database: a historical
name from the app Griglia grew out of).

**Archive** — where finished tasks go instead of being deleted: out of the list, still searchable, and
automatically after `auto_archive_days`.

## The flow with the agent

**State** — the dot at the start of the row: *waiting* (yours, the agent must not touch it), *open to work*
(ready for the agent), *working*, *question*, *paused*, *stopped*, *done*. See
[using the board](board/usage.md).

**Take** — the agent claiming a task, `griglia:check --take=ID`: the state becomes *working* and the board
shows it live.

**Progress and phase** — the percentage and the short label the agent updates while it works
(`--progress=60 --phase="writing code"`), shown as a bar on the row.

**Question** — the agent pausing a task to ask something, `--ask=ID --q="…"`. You answer in the task modal
and restart it; question and answer stay visible.

**Result** — what the agent writes when it closes a task, `--done=ID --comment="…"`, shown as a read-only
box under your note. It never touches the note, which is yours.

**Statistics** — the working time the board measures by itself (the *working* intervals) plus the tokens the
agent reports at closing. See [statistics](agent/stats.md).

**Review** — an optional second pass: a task can be assigned to a reviewer agent, which approves the result
or sends it back with remarks. See [the agent side](agent/index.md).

**Worker** — a process that keeps an agent session running unattended, picking up whatever is open to work.
See [persistent workers](agent/workers.md).

## Around the board

**Plan** — a list generated from one goal, split into a chain of tasks: closing one opens the next. See
[plans](features/plans.md).

**Skill** — a named capability of your agent that you can attach to a task, so the agent activates it while
working. See [skills](agent/skills.md).

**Context blocks** — the pieces the `/context` page assembles into the instructions file the agent reads
(`CLAUDE.md`, `AGENTS.md`, …), each one switchable. See [agent context](agent/context.md).

**Theme** — the look of the board, installable as a package or as a zip. See [themes](features/themes.md).

**Mode** — `server` (authenticated, one board per user, the default) or `local` (no authentication, global
lists, for your own machine). See [access, administrators and modes](configuration/access.md).
