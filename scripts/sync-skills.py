#!/usr/bin/env python3
"""
Read the skills the agent (Claude Code, Codex CLI, Gemini CLI…) has available on this host and import them into the
board (`griglia:skills-import`), so the task modal lists them in the «🧩 Skill» accordion.

Sources (in order): project skills (.claude/skills/*/SKILL.md), user skills (~/.claude/skills/*/SKILL.md),
installed plugins (~/.claude/plugins/installed_plugins.json → <installPath>/skills/**/SKILL.md, named «plugin:skill»),
plus the Claude Code built-in skills listed in scripts/builtin-skills.json (they are not on disk).

The SKILL.md format is portable, but a skill only exists for the agent that finds it on disk: every entry therefore
carries `agents` (the GRIGLIA_AGENTS keys allowed to use it, inferred from the folder; empty list = everyone).
~/.agents/skills is the folder shared between different CLIs → no constraint; the same skill found in several folders
merges the agents.

Usage:  scripts/sync-skills.py            # import into the container (docker exec -i … griglia:skills-import)
        scripts/sync-skills.py --print    # only print the JSON
"""
import glob, json, os, re, subprocess, sys


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


HOME = os.path.expanduser('~')
ROOT = project_root()
CONTAINER = os.environ.get('GRIGLIA_CONTAINER', 'laravel-dev-app')
# Artisan transport: 'docker' (default, `docker exec <container>`) or 'local' (PHP on the host, no Docker)
TRANSPORT = os.environ.get('GRIGLIA_TRANSPORT', 'docker')


def artisan_command(*args):
    """`php artisan …` through `docker exec` or, with GRIGLIA_TRANSPORT=local, with GRIGLIA_PHP on the host."""
    if TRANSPORT == 'local':
        return [os.environ.get('GRIGLIA_PHP', 'php'), 'artisan', *args]
    return ['docker', 'exec', '-i', '-u', os.environ.get('GRIGLIA_USER', 'www-data'), CONTAINER, 'php', 'artisan', *args]


def frontmatter(path):
    try:
        text = open(path, encoding='utf-8').read()
    except OSError:
        return {}
    m = re.match(r'^---\s*\n(.*?)\n---', text, re.S)
    fm = {}
    if m:
        for line in m.group(1).splitlines():
            mm = re.match(r'^(\w[\w-]*):\s*(.*)$', line)
            if mm:
                fm[mm.group(1)] = mm.group(2).strip().strip('"').strip("'")
    return fm


def skill(path, source, prefix='', agents=()):
    fm = frontmatter(path)
    name = fm.get('name') or os.path.basename(os.path.dirname(path))
    return {'name': prefix + name, 'description': fm.get('description', ''), 'source': source, 'agents': list(agents)}


def collect():
    out = {}

    def add(s):
        old = out.get(s['name'])
        if old is None:
            out[s['name']] = s
            return
        # Same skill found elsewhere too: it counts for both agents (empty list = for everyone)
        old['agents'] = sorted(set(old['agents']) | set(s['agents'])) if old['agents'] and s['agents'] else []

    for p in sorted(glob.glob(os.path.join(ROOT, '.claude', 'skills', '*', 'SKILL.md'))):
        add(skill(p, 'project', agents=('claude',)))
    for p in sorted(glob.glob(os.path.join(HOME, '.claude', 'skills', '*', 'SKILL.md'))):
        add(skill(p, 'user', agents=('claude',)))
    # Other CLI agents sharing the SKILL.md format: Codex CLI (~/.codex/skills, .codex/skills), the generic
    # ~/.agents/skills folder (read by several CLIs → no agent constraint), Gemini CLI (~/.gemini/skills)
    for base, label, agents in ((os.path.join(ROOT, '.codex', 'skills'), 'project (codex)', ('codex',)),
                                (os.path.join(HOME, '.codex', 'skills'), 'codex', ('codex',)),
                                (os.path.join(HOME, '.agents', 'skills'), 'agents', ()),
                                (os.path.join(HOME, '.gemini', 'skills'), 'gemini', ('gemini',))):
        for p in sorted(glob.glob(os.path.join(base, '*', 'SKILL.md'))) + sorted(glob.glob(os.path.join(base, '*', '*', 'SKILL.md'))):
            add(skill(p, label, agents=agents))
    reg = os.path.join(HOME, '.claude', 'plugins', 'installed_plugins.json')
    if os.path.isfile(reg):
        try:
            plugins = json.load(open(reg)).get('plugins', {})
        except (OSError, ValueError):
            plugins = {}
        for key, installs in plugins.items():
            plugin = key.split('@')[0]
            for inst in installs or []:
                base = inst.get('installPath')
                if not base:
                    continue
                for p in sorted(glob.glob(os.path.join(base, 'skills', '**', 'SKILL.md'), recursive=True)):
                    add(skill(p, f'plugin {plugin}', prefix=f'{plugin}:', agents=('claude',)))
    builtin = next((c for c in (os.path.join(os.path.dirname(os.path.abspath(__file__)), 'builtin-skills.json'),
                                 os.path.join(ROOT, 'scripts', 'builtin-skills.json')) if os.path.isfile(c)), '')
    if os.path.isfile(builtin):
        for s in json.load(open(builtin)):
            s.setdefault('source', 'built-in')
            s.setdefault('agents', ['claude'])  # Claude Code internals: no other agent has them
            add(s)
    return sorted(out.values(), key=lambda s: s['name'].lower())


def main():
    data = json.dumps(collect(), ensure_ascii=False, indent=1)
    if '--print' in sys.argv:
        print(data); return
    r = subprocess.run(artisan_command('griglia:skills-import'), input=data, text=True, cwd=ROOT if TRANSPORT == 'local' else None)
    sys.exit(r.returncode)


if __name__ == '__main__':
    main()
