# Quickstart

Take one request from **open to work** to **done** in a few minutes. This guide assumes you have
[installed Griglia](installation.md), published `AGENTS.md`, and created the agent list (`dev` by default).

## 1. Add a request

Open the agent list in the board and add a task such as:

> Add a health-check endpoint that returns `{"status":"ok"}`.

Click the task's state control once. It changes from **waiting** to **open to work**; only then may an agent
take it.

## 2. Run the lifecycle

From the application root, list the available work:

```bash
php artisan griglia:check
```

Copy the task ID from the output and use it in the remaining commands (this example uses `12`):

```bash
php artisan griglia:check --take=12
php artisan griglia:check --take=12 --progress=60 --phase="writing code"
php artisan griglia:check --done=12 --comment="Added the health-check endpoint and its test."
```

These are the commands your coding agent runs while following `AGENTS.md`. The board updates the task to
**working**, shows its progress, and finally marks it **done**.

## 3. Confirm the result

Open the task. The completion comment appears below your original request. Verify the change in the application
and review the agent's tests or commit as you normally would.

You have completed the full Griglia request lifecycle.

## Next steps

- [The agent side](../agent/index.md) - connect the lifecycle to Codex, Claude Code, or another CLI agent.
- [Using the board](../board/usage.md) - add notes, sub-tasks and images; ask questions; archive completed work.
- [Persistent workers](../agent/workers.md) - keep an agent waiting for new work and stop requests.
- [Configuration & settings](../configuration/index.md) - change the agent-list name and agent behaviour.
- [FAQ](../faq.md) · [Glossary](../glossary.md) - short answers, and the words this documentation uses.
