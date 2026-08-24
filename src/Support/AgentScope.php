<?php

namespace Alle80\Griglia\Support;

use Alle80\Griglia\Models\Checklist;
use Illuminate\Support\Collection;

/**
 * Which lists the agent may work on. The agent list is the channel, but the state dot means the same
 * everywhere: what the owner marks 🟢 open to work is work for the agent, wherever it sits (task 651).
 * Kept here so `griglia:check` and `griglia:watch` cannot drift apart.
 */
class AgentScope
{
    /** Plan lists of the same owner: built from a prompt, or holding chained tasks. */
    public static function plans(Checklist $agentList): Collection
    {
        return Checklist::where('user_id', $agentList->user_id)->whereKeyNot($agentList->id)->whereNull('archived_at')
            ->where(fn ($q) => $q->whereNotNull('plan_prompt')->orWhereHas('todos', fn ($t) => $t->whereNotNull('depends_on_id')))
            ->orderBy('id')->get();
    }

    /**
     * The owner's remaining lists, kept only when they hold a task the agent may act on
     * (open to work, working, or waiting for an answer). An ordinary list stays invisible.
     *
     * @param  array<int,int>  $planIds  ids already covered by plans()
     */
    public static function others(Checklist $agentList, array $planIds): Collection
    {
        return Checklist::where('user_id', $agentList->user_id)->whereKeyNot($agentList->id)->whereNull('archived_at')
            ->whereNotIn('id', $planIds)
            ->whereHas('todos', fn ($t) => $t->whereNull('archived_at')->where('completed', false)
                ->where(fn ($q) => $q->where('open_to_work', true)->orWhere('working', true)->orWhere('question', true)))
            ->orderBy('id')->get();
    }

    /**
     * Every list id in scope, in working order: agent list, plans, then the rest.
     *
     * @return array<int,int>
     */
    public static function ids(Checklist $agentList): array
    {
        $plans = self::plans($agentList)->modelKeys();

        return array_merge([$agentList->id], $plans, self::others($agentList, $plans)->modelKeys());
    }
}
