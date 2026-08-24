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

    /**
     * Models the board may offer for an agent (value => label), from config `griglia.agent_models`:
     * "claude:opus,sonnet;codex:gpt-5" per agent, a bare "opus,sonnet" for every agent, "value=Label" to
     * rename one in the UI. Empty = no picker at all: the agent CLI keeps whatever default it has.
     */
    public static function models(?string $agentKey = null): array
    {
        return self::catalogue(config('griglia.agent_models'), $agentKey);
    }

    /** Reasoning efforts offered for an agent (value => label), from config `griglia.agent_efforts`. */
    public static function efforts(?string $agentKey = null): array
    {
        return self::catalogue(config('griglia.agent_efforts'), $agentKey);
    }

    /**
     * The model the agent CLI already starts with (config `griglia.agent_default_model`, mirroring the
     * worker's own `GRIGLIA_WORKER_MODEL`): what a list or a task that chose nothing will run on. The board
     * only names it in the UI — «Default (opus)» instead of a bare «CLI default» (task 659) — and never
     * sends it, so that each worker keeps its own default. Null when unset or no longer offered.
     */
    public static function defaultModel(?string $agentKey = null): ?string
    {
        return self::declaredDefault(config('griglia.agent_default_model'), $agentKey, self::models($agentKey));
    }

    /** Reasoning effort the agent CLI already starts with (config `griglia.agent_default_effort`). */
    public static function defaultEffort(?string $agentKey = null): ?string
    {
        return self::declaredDefault(config('griglia.agent_default_effort'), $agentKey, self::efforts($agentKey));
    }

    /** First declared default the agent still offers: naming a value that is not in the picker would lie. */
    private static function declaredDefault(mixed $raw, ?string $agentKey, array $catalogue): ?string
    {
        foreach (array_keys(self::catalogue($raw, $agentKey)) as $value) {
            if (isset($catalogue[$value])) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Parses a catalogue shared by models and efforts: a string ("claude:a,b;codex:c" or just "a,b"), or an
     * array (['claude' => ['a', 'b']] / ['a', 'b']). Returns the entries of one agent, value => label.
     */
    private static function catalogue(mixed $raw, ?string $agentKey): array
    {
        $key = $agentKey !== null && $agentKey !== '' ? (self::resolve($agentKey) ?? $agentKey) : self::defaultKey();

        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return [];
            }
            $groups = [];
            foreach (explode(';', $raw) as $group) {
                $group = trim($group);
                if ($group === '') {
                    continue;
                }
                // «claude:opus,sonnet» belongs to one agent; a bare «opus,sonnet» to every one of them.
                if (str_contains($group, ':')) {
                    [$owner, $values] = array_map('trim', explode(':', $group, 2));
                    $groups[$owner !== '' ? $owner : '*'] = $values;
                } else {
                    $groups['*'] = $group;
                }
            }
            $values = $groups[$key] ?? ($groups['*'] ?? '');

            return self::entries(explode(',', (string) $values));
        }

        if (is_array($raw)) {
            // Either a map agent => values, or a flat list shared by every agent.
            $values = array_is_list($raw) ? $raw : ($raw[$key] ?? $raw['*'] ?? []);

            return self::entries((array) $values);
        }

        return [];
    }

    /** value => label from a list of «value» or «value=Label» entries. */
    private static function entries(array $items): array
    {
        $out = [];
        foreach ($items as $item => $label) {
            if (is_int($item)) {
                [$item, $label] = array_pad(array_map('trim', explode('=', (string) $label, 2)), 2, null);
            }
            $item = trim((string) $item);
            if ($item !== '') {
                $out[$item] = trim((string) $label) !== '' ? trim((string) $label) : $item;
            }
        }

        return $out;
    }

    /** Model of a todo: its own, else its list's, else null (the worker's own default). */
    public static function effectiveModel(Todo $todo, ?Checklist $list = null): ?string
    {
        return self::inherited($todo->model, ($list ?? $todo->checklist)?->model, self::models(self::effective($todo, $list)));
    }

    /** Reasoning effort of a todo: its own, else its list's, else null (the worker's own default). */
    public static function effectiveEffort(Todo $todo, ?Checklist $list = null): ?string
    {
        return self::inherited($todo->effort, ($list ?? $todo->checklist)?->effort, self::efforts(self::effective($todo, $list)));
    }

    /**
     * First value the agent still offers: a task assigned to another agent (or a catalogue that changed) must
     * not carry a model that agent knows nothing about — the worker would fail inside the CLI (task 641).
     */
    private static function inherited(?string $own, ?string $inherited, array $catalogue): ?string
    {
        foreach ([$own, $inherited] as $value) {
            if ($value && isset($catalogue[$value])) {
                return $value;
            }
        }

        return null;
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
