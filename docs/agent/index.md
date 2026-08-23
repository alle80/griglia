# The agent side

Griglia works with any coding agent that can run shell commands. Give the agent the repository instructions
(`AGENTS.md` for Codex, `CLAUDE.md` for Claude Code or `GEMINI.md` for Gemini CLI), then let it use the board
through Artisan.

## Work on a task

```bash
# 1. List available work
php artisan griglia:check --agent=codex

# 2. Mark a task as working
php artisan griglia:check --agent=codex --take=42

# 3. Record progress
php artisan griglia:check --agent=codex --take=42 --progress=60 --phase="testing"

# 4. Mark the task as completed and save the result
php artisan griglia:check --agent=codex --done=42 --comment="Implemented and tested."
```

Replace `codex` with the key configured for your agent. `check` shows only that agent's available and active
tasks, including tasks from started plans. It also prints the settings that shape the agent workflow; the
generated repository instructions explain how the agent applies them.

Questions can be attached to the task from the same command:

```bash
php artisan griglia:check --agent=codex --ask=42 \
  --q="Which layout should I update?" --choices="Board|Settings"
```

The user answers in the task modal and reopens the task. `--pause=42` records a temporary agent-side pause,
such as a usage limit.

For unattended operation, run a [persistent worker](workers.md). To react to board events yourself, use
`griglia:watch --agent=codex`; add `--once` for polling from cron.

## More detail

- [Quickstart](../getting-started/quickstart.md) — complete the first task step by step.
- [Artisan command reference](../reference/commands.md) — all commands and options, including reviews and outcomes.
- [Agent context](context.md) — generate and maintain the instruction files.
- [Persistent workers](workers.md) — run agents automatically.
- [Several agents](concurrency.md) — assignments and shared-resource coordination.
- [Skills](skills.md) · [Statistics](stats.md) · [Host scripts](scripts.md)
