# Start the agent

Installing Griglia does not start a coding agent. There are three ways to run one, ordered by how much you stay
at the keyboard. All of them share the same contract — the instruction file at the project root and
`griglia:check --agent=<key>` — so you can move down the list whenever the previous way starts to feel manual.

| Way | What it does | Choose it when |
|---|---|---|
| [1. A terminal in the project](#1-a-terminal-in-the-project-directory) | you start the agent and follow the session | the first time, always |
| [2. One command per session](#2-one-command-per-session-cli) | a non-interactive run that ends by itself | you launch it from a script, cron or CI |
| [3. A persistent worker](#3-a-persistent-worker) | a service starts a session whenever work is assigned | the board must work unattended |

## What all three need

- the agent CLI installed on the machine that holds the project — `claude`, `codex`, `gemini`, …
- an instruction file at the project root: `AGENTS.md`, `CLAUDE.md` or `GEMINI.md`. Publish the packaged one
  with `php artisan vendor:publish --tag=griglia-agents`, or generate it from `/context` (see
  [agent context](context.md))
- a list named after `agent_list` (`dev` by default) holding at least one task set to **open to work**
- the agent key the board knows (`agent_key`, `claude` in the examples below)

## 1. A terminal in the project directory

Open a terminal, move to the project root — the directory that holds `artisan` and the instruction file — and
start the agent CLI **there**:

```bash
cd /srv/my-project
claude            # Claude Code · `codex` for Codex CLI · `gemini` for Gemini CLI
```

The directory is the whole trick: the agent reads the instruction file from the current directory and runs
`php artisan` from it. An agent started somewhere else is the usual reason one «does not see» the board.

Then send the first message:

> Read AGENTS.md and work on the Griglia board as agent `claude`: run `php artisan griglia:check
> --agent=claude`, take the first task that is open to work, and follow the workflow through to closing it.

The agent runs `griglia:check`, and the task dot turns to **working** on the board while you watch. From there
the lifecycle is its job — progress, questions, completion comment — and yours is to answer when it asks. The
same session seen from the commands is [the agent side](index.md).

When the application runs in a container, Artisan commands are `docker exec <container> php artisan …`: say so
once in the instruction file and the agent will use that form everywhere.

## 2. One command per session (CLI)

Same directory, one command that runs to the end and exits. This is the shape to put in a script, a cron entry
or a CI job:

```bash
cd /srv/my-project
claude -p --permission-mode bypassPermissions \
  "Work on Griglia as agent claude. Read AGENTS.md first and obey it. Take the first task that is open to work
   with php artisan griglia:check --agent=claude, complete it, and close it with --done."
```

Codex CLI takes the prompt the same way, with its own flags and an explicit project directory:

```bash
codex exec --approve-for-me -C /srv/my-project \
  "Work on Griglia as agent codex. Read AGENTS.md first and obey it. …"
```

Those flags let the session act without asking for approval at every step: use them only on a project you
trust, and prefer the narrowest permission mode your CLI offers. Nothing watches the board in this mode — each
session starts because you (or your scheduler) started it, and handles one task.

## 3. A persistent worker

The worker repeats mode 2 on its own: it polls the board and starts a fresh session as soon as a task becomes
open to work for its agent key, one session at a time per project and key, and it honours stop requests.

```bash
php artisan vendor:publish --tag=griglia-scripts     # scripts/ and the systemd unit template
# copy scripts/systemd/griglia-agent-worker@.service.example, put the project path in it, then:
systemctl --user enable --now griglia-agent-worker@claude.service
```

The complete setup — unit template, one worker per project and agent, model and effort, usage limits, logs —
is in [persistent workers](workers.md).

## Next

- [The agent side](index.md) — the commands a session runs, from `--take` to `--done`.
- [Two agents at once](concurrency.md) — assignments and shared resources when more than one CLI is running.
- [Quickstart](../getting-started/quickstart.md) — the same lifecycle driven by hand, to see what to expect.
