#!/usr/bin/env python3
"""
Collect the plan and the usage windows of the CLI agents on this server and send them to the board (/agents) with
`griglia:agent-status-import`. The credentials stay here: only percentages and reset times reach the board.

Agents:
  - Claude Code: ~/.claude/.credentials.json (claudeAiOauth) → GET https://api.anthropic.com/api/oauth/usage
    (five_hour / seven_day: utilization %, resets_at; extra_usage). Plan from subscriptionType/rateLimitTier.
  - Codex CLI: latest `token_count.rate_limits` event in the local rollouts (no credential leaves the host).

Usage:  scripts/agent-status.py            # collect and import into the container
        scripts/agent-status.py --print    # only print the JSON
Cron:   */5 * * * * /path/to/laravel-dev/scripts/agent-status.py -q
"""
import glob, json, os, subprocess, sys, urllib.request
from datetime import datetime, timezone

HOME = os.path.expanduser('~')
CONTAINER = os.environ.get('GRIGLIA_CONTAINER', 'laravel-dev-app')
# Artisan transport: 'auto' (default: the container when it is running, PHP on this machine otherwise),
# 'docker' (`docker exec <container>`) or 'local' (`php artisan` from the project root, no Docker at all)
TRANSPORT = os.environ.get('GRIGLIA_TRANSPORT', 'auto')
_TRANSPORT = None


def project_root():
    """Root of the project using the board: $GRIGLIA_PROJECT_ROOT, otherwise the directory holding these scripts
    (<project>/scripts) or — when the script is run from vendor/alle80/griglia/scripts — the one holding `vendor`.
    Used as the working directory when Artisan runs locally."""
    env = os.environ.get('GRIGLIA_PROJECT_ROOT')
    if env:
        return os.path.abspath(os.path.expanduser(env))
    here = os.path.dirname(os.path.abspath(__file__))
    parts = here.split(os.sep)
    if 'vendor' in parts:
        return os.sep.join(parts[:parts.index('vendor')]) or os.sep
    return os.path.dirname(here)


ROOT = project_root()


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
            _TRANSPORT = 'local' if os.path.isfile(os.path.join(ROOT, 'artisan')) else 'docker'
    return _TRANSPORT


def artisan_command(*args):
    """`php artisan …` through `docker exec` or, with the local transport, with GRIGLIA_PHP on this machine."""
    if transport() == 'local':
        return [os.environ.get('GRIGLIA_PHP', 'php'), 'artisan', *args]
    return ['docker', 'exec', '-i', '-u', os.environ.get('GRIGLIA_USER', 'www-data'), CONTAINER, 'php', 'artisan', *args]


def artisan_cwd():
    """Working directory of Artisan: the project root when PHP runs here, the container's own when it is Docker."""
    return ROOT if transport() == 'local' else None


def transport_hint():
    """One line naming the transport that just failed and the variable that changes it."""
    if transport() == 'local':
        php = os.environ.get('GRIGLIA_PHP', 'php')
        return f'artisan ran here as `{php} artisan` in {ROOT}: set GRIGLIA_PHP, or GRIGLIA_TRANSPORT=docker to use a container'
    return f'artisan ran through `docker exec {CONTAINER}`: set GRIGLIA_CONTAINER, or GRIGLIA_TRANSPORT=local to use PHP on this machine'


PLAN_LABELS = {'max': 'Max', 'pro': 'Pro', 'team': 'Team', 'enterprise': 'Enterprise', 'free': 'Free'}


def claude():
    agent = {'key': 'claude', 'name': 'Claude Code', 'plan': None, 'plan_kind': None, 'windows': [], 'extra_usage': None, 'error': None}
    path = os.path.join(HOME, '.claude', '.credentials.json')
    if not os.path.isfile(path):
        agent['error'] = 'credentials not found'
        return agent
    try:
        o = json.load(open(path)).get('claudeAiOauth') or {}
    except (OSError, ValueError) as e:
        agent['error'] = f'cannot read credentials: {e}'
        return agent
    sub = (o.get('subscriptionType') or '').lower()
    tier = o.get('rateLimitTier') or ''
    mult = ''
    for part in tier.split('_'):
        if part.endswith('x') and part[:-1].isdigit():
            mult = ' ' + part
    agent['plan'] = (PLAN_LABELS.get(sub, sub.capitalize()) + mult).strip() or None
    agent['plan_kind'] = 'flat' if sub in ('max', 'pro', 'team', 'enterprise') else (sub or None)
    tok = o.get('accessToken')
    if not tok:
        agent['error'] = 'no access token'
        return agent
    req = urllib.request.Request('https://api.anthropic.com/api/oauth/usage', headers={
        'Authorization': 'Bearer ' + tok, 'anthropic-beta': 'oauth-2025-04-20', 'Accept': 'application/json', 'User-Agent': 'griglia/1.0'})
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            body = json.loads(r.read())
    except Exception as e:  # noqa: BLE001
        agent['error'] = f'usage endpoint: {e}'
        return agent
    # Labels are a fallback: the board translates the known window keys (griglia::t.agents.window.*)
    for key, label in (('five_hour', '5 hours'), ('seven_day', '7 days'), ('seven_day_opus', '7 days · Opus'), ('seven_day_sonnet', '7 days · Sonnet')):
        w = body.get(key)
        if not isinstance(w, dict):
            continue
        agent['windows'].append({'key': key, 'label': label, 'utilization': w.get('utilization'), 'resets_at': w.get('resets_at'),
                                 'limit_dollars': w.get('limit_dollars'), 'used_dollars': w.get('used_dollars')})
    extra = body.get('extra_usage')
    if isinstance(extra, dict):
        agent['extra_usage'] = {k: extra.get(k) for k in ('is_enabled', 'monthly_limit', 'used_credits', 'utilization')}
    return agent


def codex():
    if not os.path.isdir(os.path.join(HOME, '.codex')):
        return None
    agent = {'key': 'codex', 'name': 'Codex CLI', 'plan': None, 'plan_kind': None, 'windows': [], 'extra_usage': None, 'error': None}
    files = sorted(glob.glob(os.path.join(HOME, '.codex', 'sessions', '**', 'rollout-*.jsonl'), recursive=True), key=os.path.getmtime, reverse=True)
    limits = None
    for path in files[:20]:
        try:
            with open(path, encoding='utf-8') as stream:
                lines = stream.readlines()
            for line in reversed(lines):
                event = json.loads(line)
                payload = event.get('payload') or {}
                if payload.get('type') == 'token_count' and isinstance(payload.get('rate_limits'), dict):
                    limits = payload['rate_limits']; break
        except (OSError, ValueError):
            continue
        if limits:
            break
    if not limits:
        agent['error'] = 'usage telemetry not found'
        return agent
    plan = str(limits.get('plan_type') or '').lower()
    agent['plan'] = PLAN_LABELS.get(plan, plan.capitalize()) or None
    agent['plan_kind'] = plan or None
    for key, label in (('secondary', '5 hours'), ('primary', '7 days')):
        window = limits.get(key)
        if not isinstance(window, dict):
            continue
        minutes = window.get('window_minutes')
        if minutes == 300: label = '5 hours'
        elif minutes == 10080: label = '7 days'
        reset = window.get('resets_at')
        agent['windows'].append({'key': key, 'label': label, 'utilization': window.get('used_percent'),
                                 'resets_at': datetime.fromtimestamp(reset, timezone.utc).isoformat() if isinstance(reset, (int, float)) else None})
    return agent


def main():
    agents = [a for a in (claude(), codex()) if a]
    data = {'updated_at': datetime.now(timezone.utc).isoformat(), 'agents': agents}
    payload = json.dumps(data, ensure_ascii=False, indent=1)
    if '--print' in sys.argv:
        print(payload); return
    try:
        r = subprocess.run(artisan_command('griglia:agent-status-import'), input=payload, text=True, capture_output=True, cwd=artisan_cwd())
    except OSError as e:
        sys.exit(f'cannot run the import: {e}\n{transport_hint()}')
    if '-q' not in sys.argv:
        print((r.stdout or r.stderr).strip() + ('' if r.returncode == 0 else '\n' + transport_hint()))
    sys.exit(r.returncode)


if __name__ == '__main__':
    main()
