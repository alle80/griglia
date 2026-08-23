# Glossary

The words used across this documentation, the interface and the Artisan commands. Where the code uses a
different name, it is noted — those names are historical and are not going to change. For the questions
behind these words, see the [FAQ](faq.md).

## The board

**Board** — the page Griglia serves at `/`: one list at a time, its tasks, and the controls to filter,
search and switch list.

**List** — a named set of tasks belonging to a user (`Checklist` in the code). The current list is kept in
the session, so every query is scoped to one user and one list.

**Agent list** — the list a coding agent reads, `agent_list` in the configuration (`dev` by default). Other
lists are yours; the agent ignores them.

**Task** — one row of the board (`Todo` in the code): a title, an optional note, sub-tasks, images, a state
and, once it is done, the agent's result.

**Sub-task** — a checklist item inside a task, listed under the note in the task modal: the agent ticks
them off as it goes, and can tick them all when it closes the task.

**Ingredient** — the sub-task, seen from the code: the `Ingredient` model, the `ingredients` table and the
`IngredientModal` component. A historical name from the app Griglia grew out of, kept because renaming it
would break every installed database — where the documentation says *sub-task*, the code says *ingredient*.

**Archive** — where finished tasks go instead of being deleted: out of the list, still searchable, and
automatically after `auto_archive_days`.

## The flow with the agent

**Agent** — the CLI coding assistant that reads the board through `griglia:check`. Griglia is agent-neutral:
each one is configured with an **agent key** (`--agent=claude`, `--agent=codex`, …) which decides what it
sees, which tasks it may claim, and the name shown on the board.

**State** — the dot at the start of the row. It says who owns the task right now:

| Dot | State | Who sets it |
|-----|-------|-------------|
| ![waiting](images/state-waiting.svg){ width="18" } | waiting | you — the agent must not touch it |
| ![open to work](images/state-open.svg){ width="18" } | open to work | you — released, the agent may claim it |
| ![working](images/state-working.svg){ width="18" } | working | the agent (`--take`), with progress and phase |
| ![paused](images/state-paused.svg){ width="18" } | paused | the agent (`--pause`) — progress is kept |
| ![question](images/state-question.svg){ width="18" } | question | the agent (`--ask`) — it is waiting for your answer |
| ![stopped](images/state-stop.svg){ width="18" } | stopped | you, tapping the working badge — the agent drops it |
| ![done](images/state-done.svg){ width="18" } | done | the agent (`--done`) or you (checkbox) |

See [using the board](board/usage.md).

**Take** — the agent claiming a task, `griglia:check --take=ID`: the state becomes *working* and the board
shows it live.

**Progress and phase** — the percentage and the short label the agent updates while it works
(`--progress=60 --phase="writing code"`), shown as a bar on the row.

**Question** — the agent pausing a task to ask something, `--ask=ID --q="…"`. You answer in the task modal
and restart it; question and answer stay visible.

**Result** — what the agent writes when it closes a task, `--done=ID --comment="…"`, shown as a read-only
box under your note. It never touches the note, which is yours.

**Statistics** — the working time the board measures by itself (the *working* intervals) plus the tokens the
agent reports at closing, priced with the rates in Settings → App. See [statistics](agent/stats.md).

**Review** — an optional second pass: a task can be assigned to a reviewer agent, which approves the result
or sends it back with remarks. See [the agent side](agent/index.md).

**Worker** — a host process that keeps an agent working unattended: it polls the board and starts a fresh
non-interactive agent session for whatever is open to work. See [persistent workers](agent/workers.md).

## Around the board

**Plan** — a list generated from one goal, split into a chain of tasks: closing one opens the next. See
[plans](features/plans.md).

**Skill** — a named capability of your agent that you can attach to a task, so the agent activates it while
working. See [skills](agent/skills.md).

**Context blocks** — the pieces the `/context` page assembles into the instructions file the agent reads
(`CLAUDE.md`, `AGENTS.md`, …), each one switchable. See [agent context](agent/context.md).

**Theme** — the look of the board: colours, spacing and shape, through CSS variables. It is installable as a
package or as a zip, and changing it does not change the markup. See [themes](features/themes.md).

**Style** — a step beyond a theme: its own Livewire components and Blade views, so the board can be laid out
differently rather than only recoloured. See [extending Griglia](configuration/extending.md#a-dedicated-style-your-own-components).

**Mode** — `server` (authenticated, one board per user, the default) or `local` (no authentication, global
lists, for your own machine). See [access, administrators and modes](configuration/access.md).
