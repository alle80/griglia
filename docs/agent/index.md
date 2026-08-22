# The agent side

Griglia works with any coding agent that can run shell commands. Give the agent the repository instructions
(`AGENTS.md` for Codex, `CLAUDE.md` for Claude Code or `GEMINI.md` for Gemini CLI), then let it use the board
through Artisan.

## Work on a task

```bash
# 1. Find work and read the current rules
php artisan griglia:check --agent=codex

# 2. Take the task before analysing it
php artisan griglia:check --agent=codex --take=42

# 3. Keep the board informed
php artisan griglia:check --agent=codex --take=42 --progress=60 --phase="testing"

# 4. Close it with a useful answer
php artisan griglia:check --agent=codex --done=42 --comment="Implemented and tested."
```

Replace `codex` with the key configured for your agent. `check` shows only that agent's available and active
tasks, including tasks from started plans. It also prints the current working rules: follow them.

If the request is unclear, ask from the board instead of guessing:

```bash
php artisan griglia:check --agent=codex --ask=42 \
  --q="Which layout should I update?" --choices="Board|Settings"
```

The user answers in the task modal and reopens the task. Use `--pause=42` only for a temporary agent-side
pause, such as a usage limit.

## Rules that matter

- Take a task before reading or analysing its details.
- Work only on open tasks assigned to the agent. Never touch waiting or stopped tasks.
- Follow the order and concurrency policy printed by `check`.
- Keep progress and phase current while working.
- Include token counts on `--done` when the settings request them.
- Use `--outcome=alert` or `--outcome=blocked` when a completed task still needs attention.
- Coordinate shared checkouts, builds, migrations and releases when several agents are active.

For unattended operation, run a [persistent worker](workers.md). To react to board events yourself, use
`griglia:watch --agent=codex`; add `--once` for polling from cron.

## More detail

- [Quickstart](../getting-started/quickstart.md) — complete the first task step by step.
- [Artisan command reference](../reference/commands.md) — all commands and options, including reviews and outcomes.
- [Agent context](context.md) — generate and maintain the instruction files.
- [Persistent workers](workers.md) — run agents automatically.
- [Several agents](concurrency.md) — assignments and shared-resource coordination.
- [Skills](skills.md) · [Statistics](stats.md) · [Host scripts](scripts.md)
