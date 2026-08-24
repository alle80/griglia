#!/usr/bin/env python3
"""
Count the REAL tokens Claude Code spent in this session from a given instant on
(typically the `working_since` of a task in the sviluppo list), by reading the JSONL
transcript Claude Code writes to ~/.claude/projects/<project>/<session>.jsonl
(every assistant message carries the API `usage` block).

Usage (from the host, where the transcript lives):
  scripts/claude-tokens.py --since 2026-08-19T08:12:00+02:00          # in=… out=…
  scripts/claude-tokens.py --todo 180 --args                          # reads working_since from the DB via artisan
      → prints "--tokens-in=N --tokens-out=N", to paste into `griglia:check --done=… --tokens-in… --tokens-out…`
  scripts/claude-tokens.py --context                                  # how heavy the session context is RIGHT NOW

The context is re-read every turn: once it gets heavy, every single step costs more. With
--warn-at=N (thousands of tokens, default 400; 0 = never) the script prints a reminder on STDERR to pass on
to the user — only they can run /clear.

Token "in" = input_tokens + cache_creation_input_tokens + cache_read_input_tokens (everything the model
read); "out" = output_tokens. Duplicate records of the same message (same `message.id`) count once.
"""
import argparse, glob, json, os, subprocess, sys
from datetime import datetime, timezone


def project_root():
    """Root of the project using the board: $GRIGLIA_PROJECT_ROOT, otherwise the directory holding these scripts
    (<project>/scripts, where `vendor:publish --tag=griglia-scripts` puts them) or — when the script is run straight
    from vendor/alle80/griglia/scripts — the directory holding `vendor`."""
    env = os.environ.get('GRIGLIA_PROJECT_ROOT')
    if env:
        return os.path.abspath(os.path.expanduser(env))
    here = os.path.dirname(os.path.abspath(__file__))
    parts = here.split(os.sep)
    if 'vendor' in parts:
        return os.sep.join(parts[:parts.index('vendor')]) or os.sep
    return os.path.dirname(here)


REPO = project_root()
CONTAINER = os.environ.get('GRIGLIA_CONTAINER', 'laravel-dev-app')
# Artisan transport: 'auto' (default: the container when it is running, PHP on this machine otherwise),
# 'docker' (`docker exec <container>`) or 'local' (`php artisan` from the project root, no Docker at all)
TRANSPORT = os.environ.get('GRIGLIA_TRANSPORT', 'auto')
_TRANSPORT = None


def container_running():
    """True when the Docker daemon answers and $GRIGLIA_CONTAINER is up; false on any failure (no docker
    binary, daemon down, container stopped) — that is the signal to fall back to PHP on this machine."""
    try:
        probe = subprocess.run(['docker', 'inspect', '-f', '{{.State.Running}}', CONTAINER],
                               text=True, capture_output=True, timeout=20)
    except (OSError, subprocess.SubprocessError):
        return False
    return probe.returncode == 0 and probe.stdout.strip() == 'true'


def transport():
    """The transport actually in use, decided once per run: GRIGLIA_TRANSPORT when it names one, otherwise
    Docker only if the container answers. So a machine without Docker — Laravel served by `composer dev`,
    Apache or nginx — works out of the box, as long as `artisan` sits in the project root."""
    global _TRANSPORT
    if _TRANSPORT is None:
        if TRANSPORT in ('docker', 'local'):
            _TRANSPORT = TRANSPORT
        elif container_running():
            _TRANSPORT = 'docker'
        else:
            _TRANSPORT = 'local' if os.path.isfile(os.path.join(REPO, 'artisan')) else 'docker'
    return _TRANSPORT


def artisan_command(*args):
    """`php artisan …` through `docker exec` or, with the local transport, with GRIGLIA_PHP on this machine."""
    if transport() == 'local':
        return [os.environ.get('GRIGLIA_PHP', 'php'), 'artisan', *args]
    return ['docker', 'exec', CONTAINER, 'php', 'artisan', *args]


def artisan_cwd():
    """Working directory of Artisan: the project root when PHP runs here, the container's own when it is Docker."""
    return REPO if transport() == 'local' else None


def transport_hint():
    """One line naming the transport that just failed and the variable that changes it."""
    if transport() == 'local':
        php = os.environ.get('GRIGLIA_PHP', 'php')
        return f'artisan ran here as `{php} artisan` in {REPO}: set GRIGLIA_PHP, or GRIGLIA_TRANSPORT=docker to use a container'
    return f'artisan ran through `docker exec {CONTAINER}`: set GRIGLIA_CONTAINER, or GRIGLIA_TRANSPORT=local to use PHP on this machine'


# Claude Code stores transcripts under ~/.claude/projects/<repo path with / replaced by ->/
PROJECT_DIR = os.path.expanduser('~/.claude/projects/' + os.environ.get('CLAUDE_PROJECT_SLUG', '-' + REPO.strip('/').replace('/', '-')))


def transcript_path(a) -> str:
    """The transcript to read: the one given with --session, otherwise the most recent one of the project."""
    if a.agent == 'codex':
        files = sorted(glob.glob(os.path.expanduser('~/.codex/sessions/**/rollout-*.jsonl'), recursive=True), key=os.path.getmtime)
    else:
        files = [os.path.join(PROJECT_DIR, a.session + '.jsonl')] if a.session else sorted(glob.glob(os.path.join(PROJECT_DIR, '*.jsonl')), key=os.path.getmtime)
    if not files or not os.path.isfile(files[-1]):
        sys.exit(f'no transcript found in {PROJECT_DIR}')
    return files[-1]


def parse_ts(s: str) -> datetime:
    s = s.strip().replace('Z', '+00:00')
    dt = datetime.fromisoformat(s)
    return dt if dt.tzinfo else dt.replace(tzinfo=timezone.utc)


def working_since_of(todo_id: int) -> str:
    try:
        out = subprocess.check_output(artisan_command('griglia:check', '--json', '--all'), text=True, cwd=artisan_cwd())
    except (OSError, subprocess.CalledProcessError) as e:
        sys.exit(f'cannot read the board: {e}\n{transport_hint()}')
    for t in json.loads(out):
        if int(t['id']) == todo_id:
            if not t.get('working_since'):
                sys.exit(f'todo {todo_id} is not working (working_since is empty): use --since')
            return t['working_since']
    sys.exit(f'todo {todo_id} not found in the agent list')


def codex_usage(since: datetime):
    """Best effort for Codex CLI: rollouts in ~/.codex/sessions/**/rollout-*.jsonl carry `token_count` events
    (payload.info.total_token_usage / last_token_usage). Sums the per-turn `last_token_usage` after `since`."""
    base = os.path.expanduser('~/.codex/sessions')
    files = sorted(glob.glob(os.path.join(base, '**', 'rollout-*.jsonl'), recursive=True), key=os.path.getmtime)
    if not files:
        return 0, 0, 0, None
    path = files[-1]
    tin = tout = n = 0
    with open(path, encoding='utf-8') as fh:
        for line in fh:
            try:
                o = json.loads(line)
            except json.JSONDecodeError:
                continue
            p = o.get('payload') or {}
            if o.get('type') != 'event_msg' or p.get('type') != 'token_count':
                continue
            ts = o.get('timestamp')
            if ts and parse_ts(ts) < since:
                continue
            u = ((p.get('info') or {}).get('last_token_usage')) or {}
            if not u:
                continue
            n += 1
            tin += int(u.get('input_tokens', 0)) + int(u.get('cached_input_tokens', 0))
            tout += int(u.get('output_tokens', 0)) + int(u.get('reasoning_output_tokens', 0))
    return tin, tout, n, path


def context_size(path: str, agent: str) -> int:
    """How heavy the context is now: the input of the last turn (prompt + re-read cache)."""
    last = 0
    with open(path, encoding='utf-8') as fh:
        for line in fh:
            try:
                o = json.loads(line)
            except json.JSONDecodeError:
                continue
            if agent == 'codex':
                p = o.get('payload') or {}
                if o.get('type') != 'event_msg' or p.get('type') != 'token_count':
                    continue
                u = ((p.get('info') or {}).get('last_token_usage')) or {}
                if u:
                    last = int(u.get('input_tokens', 0)) + int(u.get('cached_input_tokens', 0))
                continue
            if o.get('type') != 'assistant':
                continue
            u = (o.get('message') or {}).get('usage')
            if u:
                last = int(u.get('input_tokens', 0)) + int(u.get('cache_creation_input_tokens', 0)) + int(u.get('cache_read_input_tokens', 0))
    return last


def warn_if_heavy(path: str, agent: str, warn_at_k: int) -> None:
    if warn_at_k <= 0 or not path:
        return
    size = context_size(path, agent)
    if size >= warn_at_k * 1000:
        print(f'⚠ context ~{round(size / 1000)}k tokens (threshold {warn_at_k}k): tell the user to run /clear '
              f'before the next task — the context is re-read every turn.', file=sys.stderr)


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--since', help='ISO timestamp: count assistant messages from here on')
    ap.add_argument('--todo', type=int, help='id of the todo: use its working_since (via artisan --json)')
    ap.add_argument('--session', help='session id (default: the transcript most recently modified)')
    ap.add_argument('--args', action='store_true', help='print as griglia:check options (--tokens-in=N --tokens-out=N)')
    ap.add_argument('--agent', choices=['claude', 'codex'], default=os.environ.get('GRIGLIA_AGENT', 'claude'), help='which agent wrote the transcript (default claude; codex = ~/.codex/sessions rollouts)')
    ap.add_argument('--context', action='store_true', help='print how heavy the session context is RIGHT NOW (last turn)')
    ap.add_argument('--warn-at', type=int, default=int(os.environ.get('GRIGLIA_CLEAR_REMINDER_K', 400)), help='thousands of tokens above which the /clear reminder is printed on stderr (0 = never)')
    a = ap.parse_args()
    if not a.since and not a.todo and not a.context:
        ap.error('--since, --todo or --context is required')

    if a.context and not a.since and not a.todo:
        path = transcript_path(a)
        size = context_size(path, a.agent)
        print(f'context={size} (~{round(size / 1000)}k) transcript={os.path.basename(path)}')
        warn_if_heavy(path, a.agent, a.warn_at)
        return

    since = parse_ts(a.since or working_since_of(a.todo))

    if a.agent == 'codex':
        tin, tout, n, path = codex_usage(since)
        if a.args:
            print(f'--tokens-in={tin} --tokens-out={tout}')
        else:
            print(f'in={tin} out={tout} events={n} since={since.isoformat()} transcript={os.path.basename(path) if path else "-"}')
        if path:
            warn_if_heavy(path, 'codex', a.warn_at)
        return

    path = transcript_path(a)

    seen, tin, tout, n = set(), 0, 0, 0
    with open(path, encoding='utf-8') as fh:
        for line in fh:
            try:
                o = json.loads(line)
            except json.JSONDecodeError:
                continue
            if o.get('type') != 'assistant':
                continue
            m = o.get('message') or {}
            u = m.get('usage')
            if not u or not o.get('timestamp'):
                continue
            if parse_ts(o['timestamp']) < since:
                continue
            key = m.get('id') or o.get('uuid')
            if key in seen:
                continue
            seen.add(key)
            n += 1
            tin += int(u.get('input_tokens', 0)) + int(u.get('cache_creation_input_tokens', 0)) + int(u.get('cache_read_input_tokens', 0))
            tout += int(u.get('output_tokens', 0))

    if a.args:
        print(f'--tokens-in={tin} --tokens-out={tout}')
    else:
        ctx = context_size(path, 'claude')
        print(f'in={tin} out={tout} messages={n} context={ctx} since={since.isoformat()} transcript={os.path.basename(path)}')

    warn_if_heavy(path, 'claude', a.warn_at)


if __name__ == '__main__':
    main()
