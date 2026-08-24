<?php

namespace Alle80\Griglia;

use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Models\Todo;

/**
 * The coding agents driven by the board — any CLI agent works (Claude Code, Codex CLI, Gemini CLI, …): the
 * board only talks through `griglia:check`/`griglia:watch`, AGENTS.md and the generated context file.
 *
 * Several agents can be active at once: config `griglia.agents` (key => label, e.g. claude => Claude Code) lists
 * them; a list (project) has a default agent (`checklists.agent`) and a task may override it (`todos.agent`);
 * each agent runs `griglia:check --agent=<key>` (or GRIGLIA_AGENT_KEY) and sees only its tasks.
 * `agent_name` stays the generic label of "the agent" in the UI when there is only one.
 */
class Agent
{
    public static function name(): string
    {
        return (string) (config('griglia.agent_name') ?: 'Agent');
    }

    /** key => label of the configured agents (at least one: the default one). */
    public static function all(): array
    {
        $raw = config('griglia.agents');
        $out = [];
        if (is_string($raw) && trim($raw) !== '') {
            foreach (explode(',', $raw) as $pair) {
                [$k, $l] = array_pad(array_map('trim', explode(':', $pair, 2)), 2, null);
                if ($k !== '') {
                    $out[$k] = $l ?: $k;
                }
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $k => $l) {
                if (is_int($k)) {
                    $out[(string) $l] = (string) $l;
                } else {
                    $out[(string) $k] = (string) $l;
                }
            }
        }
        if ($out === []) {
            $out[self::defaultKey()] = self::name();
        }

        return $out;
    }

    /** Key of the default agent (config griglia.agent_key, else the first configured, else 'agent'). */
    public static function defaultKey(): string
    {
        $key = (string) (config('griglia.agent_key') ?: '');
        if ($key !== '') {
            return $key;
        }
        $raw = config('griglia.agents');
        if (is_string($raw) && trim($raw) !== '') {
            return trim(explode(':', explode(',', $raw)[0], 2)[0]);
        }
        if (is_array($raw) && $raw !== []) {
            $k = array_key_first($raw);

            return is_int($k) ? (string) $raw[$k] : (string) $k;
        }

        return 'agent';
    }

    public static function many(): bool
    {
        return count(self::all()) > 1;
    }

    public static function label(?string $key): string
    {
        return self::all()[$key] ?? ($key ?: self::name());
    }

    /**
     * The agent key behind whatever the caller wrote: a key, its label, another spelling («CLAUDE», «Claude
     * Code», «Claude»). A name is not a key — `--agent=Claude` used to fall through to the default agent, so
     * the ownership guard compared two spellings of the SAME agent and refused every task (task 652).
     * Returns null when nothing matches, or when the text matches more than one agent.
     */
    public static function resolve(?string $keyOrLabel): ?string
    {
        $raw = trim((string) $keyOrLabel);
        if ($raw === '') {
            return null;
        }

        $all = self::all();
        if (isset($all[$raw])) {
            return $raw;
        }

        $norm = fn (string $s) => (string) preg_replace('/[^a-z0-9]+/', '', mb_strtolower($s));
        $want = $norm($raw);
        if ($want === '') {
            return null;
        }

        $exact = [];
        $prefix = [];
        foreach ($all as $key => $label) {
            $forms = array_filter([$norm((string) $key), $norm((string) $label)]);
            if (in_array($want, $forms, true)) {
                $exact[(string) $key] = true;
            } elseif (array_filter($forms, fn ($f) => str_starts_with($f, $want))) {
                $prefix[(string) $key] = true;
            }
        }

        $hit = $exact ?: $prefix;

        return count($hit) === 1 ? (string) array_key_first($hit) : null;
    }

    /**
     * The agent key a command runs as, from its `--agent` option: any spelling of a configured agent, else
     * the default when several are configured, else '' (one agent = no filter, no ownership guard).
     * Returns null when the option names no configured agent and there are several: the caller must stop
     * rather than run as nobody — with an unknown key every task looks like somebody else's (task 652).
     */
    public static function fromOption(?string $option): ?string
    {
        $raw = trim((string) $option);
        if ($raw === '') {
            return self::many() ? self::defaultKey() : '';
        }

        return self::resolve($raw) ?? (self::many() ? null : '');
    }

    /** «claude (Claude Code), codex (Codex CLI)» — the configured agents, for error messages. */
    public static function listing(): string
    {
        return implode(', ', array_map(fn ($k, $l) => $k === $l ? $k : sprintf('%s (%s)', $k, $l), array_keys(self::all()), self::all()));
    }

    /** Effective agent key of a todo: its own, else its list's, else the default. */
    public static function effective(Todo $todo, ?Checklist $list = null): string
    {
        $list ??= $todo->checklist;

        // A key that is not configured any more (agent removed from GRIGLIA_AGENTS) would belong to nobody:
        // the task would be invisible to every agent, waiting forever. It falls back instead (task 347).
        // A label stored where a key belongs («Claude Code») still resolves to its key (task 652).
        foreach ([$todo->agent, $list?->agent] as $key) {
            if ($key && ($resolved = self::resolve((string) $key)) !== null) {
                return $resolved;
            }
        }

        return self::defaultKey();
    }
}
