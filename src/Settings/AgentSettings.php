<?php

namespace Alle80\Griglia\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Settings that drive how the coding agent works on the agent list (config griglia.agent_list).
 * Edited from /settings; `griglia:check` prints them first so the agent follows them.
 * To add one: property here + default in a settings migration + entry in fields() and in the translations.
 */
class AgentSettings extends Settings
{
    /** Commit automatically when each task is closed. */
    public bool $commit_after_task;

    /** Push to GitHub after the automatic commit (when off: commit yes, push only on request). */
    public bool $push_after_commit;

    /** Question level (task 499): autonomous | essential | ask | many | paranoid — rules in Support\QuestionLevel. */
    public string $autonomy;

    /** Push notification on the phone when it closes a task. */
    public bool $notify_on_done;

    /** Push notification on the phone when it asks a question ❓. */
    public bool $notify_on_question;

    /** Before closing: mobile+desktop screenshots and automatic Livewire tests. */
    public bool $verify_before_close;

    /** 'short' = essential 🤖 comment; 'detailed' = with technical details and how to try it. */
    public string $comment_detail;

    /** Tone and readability of the answers addressed to the user. */
    public string $response_tone;

    /** Desired length of the answers addressed to the user. */
    public string $response_length;

    /** 'main' = commits straight on main; 'branch_pr' = one branch per task + a Pull Request on GitHub. */
    public string $git_flow;

    /** Evening summary by push (what was closed during the day). */
    public bool $daily_summary;

    /** Time of the evening summary (HH:MM). */
    public string $daily_summary_time;

    /** When a task is closed, tick every sub-task automatically. */
    public bool $check_subtasks_on_done;

    /** 'ordered' = one task at a time, in order; 'multitasking' = several tasks in parallel (with care). */
    public string $task_mode;

    public static function group(): string
    {
        return 'agent';
    }

    /**
     * Fields for the settings page and griglia:check.
     * key => [label, help, type (bool|select|int|text|time), options (select: value => label)]
     * Labels/help/options come from the translations (griglia::t.settings_fields / settings_options).
     */
    public static function fields(): array
    {
        $types = [
            'commit_after_task' => 'bool', 'push_after_commit' => 'bool', 'autonomy' => 'select', 'notify_on_done' => 'bool',
            'notify_on_question' => 'bool', 'verify_before_close' => 'bool', 'comment_detail' => 'select',
            'response_tone' => 'select', 'response_length' => 'select', 'git_flow' => 'select',
            'daily_summary' => 'bool', 'daily_summary_time' => 'time', 'check_subtasks_on_done' => 'bool',
            'task_mode' => 'select',
        ];
        $labels = (array) __('griglia::t.settings_fields');
        $options = (array) __('griglia::t.settings_options');
        $out = [];
        foreach ($types as $key => $type) {
            [$label, $help] = $labels[$key] ?? [$key, ''];
            $out[$key] = [$label, $help, $type, $type === 'select' ? ($options[$key] ?? []) : []];
        }

        return $out;
    }

    /** Compact one-liner for griglia:check. */
    public function summary(): string
    {
        $out = [];
        foreach (self::fields() as $key => $f) {
            $v = $this->{$key};
            $out[] = $f[0].': '.match ($f[2]) {
                'bool' => $v ? __('griglia::t.yes') : __('griglia::t.no'),
                'select' => $f[3][$v] ?? $v,
                default => (string) $v,
            };
        }

        return implode(' · ', $out);
    }
}
