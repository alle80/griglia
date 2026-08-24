<?php

namespace Alle80\Griglia\Livewire;

use Alle80\Griglia\Agent;
use Alle80\Griglia\Mode;
use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Models\Todo;
use Alle80\Griglia\Settings\AppSettings;
use Alle80\Griglia\Themes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class TodoList extends Component
{
    /** The `order` value the new todo will take (null = no insertion in progress). */
    public ?int $insertAt = null;

    public string $newTitle = '';

    /** Archive view (true) or active list (false). */
    public bool $showArchived = false;

    /** Free-text search (title, note, comment, sub-tasks, images). */
    public string $search = '';

    /** Show todos from every active list owned by the current user. */
    public bool $searchAllLists = false;

    /** Status filter: all | todo | done | otw | working | paused | question */
    public string $filter = 'all';

    /** Effective agent key, or '' for every configured agent. */
    public string $agentFilter = '';

    /** Maximum length of a todo title: 50 by default, changeable in /settings. */
    public const TITLE_MAX = 50;

    public static function titleMax(): int
    {
        return (int) (app(AppSettings::class)->title_max_length ?: self::TITLE_MAX);
    }

    /** Filter keys (labels come from the translations: griglia::t.filters). */
    public const FILTERS = ['all', 'todo', 'done', 'otw', 'working', 'paused', 'question'];

    /** key => translated label */
    public static function filters(): array
    {
        $labels = (array) __('griglia::t.filters');

        return array_combine(self::FILTERS, array_map(fn ($k) => $labels[$k] ?? $k, self::FILTERS));
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }

    public function setAgentFilter(string $agent): void
    {
        $this->agentFilter = array_key_exists($agent, Agent::all()) ? $agent : '';
    }

    public function clearSearch(): void
    {
        $this->search = '';
    }

    public function toggleSearchScope(): void
    {
        $this->searchAllLists = ! $this->searchAllLists;
    }

    /** Apply the search and the status filter to a todo query. */
    protected function applyFilters(Builder $q): Builder
    {
        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $q->where(function (Builder $w) use ($like) {
                $w->where('title', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('claude_comment', 'like', $like)
                    ->orWhereHas('ingredients', fn ($i) => $i->where('name', 'like', $like))
                    ->orWhereHas('questions', fn ($qq) => $qq->where('question', 'like', $like)->orWhere('answer', 'like', $like))
                    ->orWhereHas('attachments', fn ($a) => $a->where('original_name', 'like', $like)->orWhere('description', 'like', $like));
            });
        }

        if ($this->agentFilter !== '') {
            $agent = $this->agentFilter;
            $known = array_keys(Agent::all());
            $fallback = Agent::defaultKey();
            $q->where(function (Builder $w) use ($agent, $known, $fallback) {
                $w->where('agent', $agent)
                    ->orWhere(function (Builder $inherited) use ($agent, $known, $fallback) {
                        $inherited->where(fn (Builder $task) => $task->whereNull('agent')->orWhereNotIn('agent', $known))
                            ->whereHas('checklist', function (Builder $list) use ($agent, $known, $fallback) {
                                $list->where('agent', $agent);
                                if ($agent === $fallback) {
                                    $list->orWhereNull('agent')->orWhereNotIn('agent', $known);
                                }
                            });
                    });
            });
        }

        return match ($this->filter) {
            'todo' => $q->where('completed', false),
            'done' => $q->where('completed', true),
            'otw' => $q->where('open_to_work', true)->where('completed', false),
            'working' => $q->where('working', true)->where('completed', false),
            'paused' => $q->where('paused', true)->where('completed', false),
            'question' => $q->where('question', true),
            default => $q,
        };
    }

    protected function isFiltering(): bool
    {
        return $this->searchAllLists || trim($this->search) !== '' || $this->filter !== 'all' || $this->agentFilter !== '';
    }

    /** Todo being renamed and its draft. */
    public ?int $editingId = null;

    public string $titleDraft = '';

    /** Title as it was when the rename started: saving is live, «undo» puts this one back. */
    public ?string $titleOriginal = null;

    /** Query of the todos of the current list. */
    protected function scoped(): Builder
    {
        if ($this->searchAllLists) {
            return Todo::whereIn('checklist_id', Checklist::mine()->select('id'));
        }

        return Todo::where('checklist_id', Checklist::currentId());
    }

    /** Todos of the current list, ordered, with sub-tasks: used by the render of every variant. */
    protected function todos(): Collection
    {
        return $this->applyFilters($this->scoped())
            ->when($this->showArchived, fn ($q) => $q->whereNotNull('archived_at')->orderByDesc('archived_at'), fn ($q) => $q->whereNull('archived_at')->orderBy('order'))
            ->with(['checklist:id,name,agent,model,effort', 'ingredients', 'dependsOn:id,title,completed,order'])->withCount('attachments')->get();
    }

    /** Query of the active (not archived) todos of the current list: the `order` numbering lives only here. */
    protected function active(): Builder
    {
        return Todo::where('checklist_id', Checklist::currentId())->whereNull('archived_at');
    }

    /** Multi-agent: default agent of the current list ('' = the global default). */
    public function setListAgent(string $agent): void
    {
        $agent = trim($agent);
        if ($agent !== '' && ! array_key_exists($agent, Agent::all())) {
            return;
        }
        Checklist::mine()->whereKey(Checklist::currentId())->update(['agent' => $agent ?: null]);
        $this->dispatch('toast', message: __('griglia::t.agent_set', ['agent' => Agent::label($agent ?: Agent::defaultKey())]));
    }

    /** Multi-agent: agent of a single task from the list row ('' = the list's default). */
    public function setTodoAgent(int $todoId, string $agent): void
    {
        $agent = trim($agent);
        if ($agent !== '' && ! array_key_exists($agent, Agent::all())) {
            return;
        }
        $todo = $this->scoped()->findOrFail($todoId);
        if ($todo->working) {
            return;
        }
        $todo->update(['agent' => $agent ?: null]);
        $this->dispatch('toast', message: __('griglia::t.agent_set_task', [
            'title' => $todo->title,
            'agent' => Agent::label(Agent::effective($todo)),
        ]));
    }

    /** Model of the sessions of the current list ('' = the CLI's own default). */
    public function setListModel(string $model): void
    {
        $this->setListPreset('model', $model);
    }

    /** Reasoning effort of the sessions of the current list ('' = the CLI's own default). */
    public function setListEffort(string $effort): void
    {
        $this->setListPreset('effort', $effort);
    }

    /** Model of a single task from the list row ('' = the list's default). */
    public function setTodoModel(int $todoId, string $model): void
    {
        $this->setTodoPreset($todoId, 'model', $model);
    }

    /** Reasoning effort of a single task from the list row ('' = the list's default). */
    public function setTodoEffort(int $todoId, string $effort): void
    {
        $this->setTodoPreset($todoId, 'effort', $effort);
    }

    /** Model/effort of the list: only values the list's agent offers (task 641). */
    private function setListPreset(string $field, string $value): void
    {
        $list = Checklist::mine()->whereKey(Checklist::currentId())->first();
        if (! $list) {
            return;
        }
        $value = trim($value);
        $catalogue = $field === 'model' ? Agent::models($list->agent ?: Agent::defaultKey()) : Agent::efforts($list->agent ?: Agent::defaultKey());
        if ($value !== '' && ! isset($catalogue[$value])) {
            return;
        }
        $list->update([$field => $value ?: null]);
        $this->dispatch('toast', message: __('griglia::t.'.$field.'_set', [
            $field => $value !== '' ? $catalogue[$value] : self::presetDefaultLabel($field, $list->agent ?: Agent::defaultKey(), $catalogue),
        ]));
    }

    /** Model/effort of a task from its row: only values the task's agent offers, and never while it works. */
    private function setTodoPreset(int $todoId, string $field, string $value): void
    {
        $todo = $this->scoped()->findOrFail($todoId);
        if ($todo->working) {
            return;
        }
        $value = trim($value);
        $catalogue = $field === 'model' ? Agent::models(Agent::effective($todo)) : Agent::efforts(Agent::effective($todo));
        if ($value !== '' && ! isset($catalogue[$value])) {
            return;
        }
        $todo->update([$field => $value ?: null]);
        $effective = $field === 'model' ? Agent::effectiveModel($todo->fresh()) : Agent::effectiveEffort($todo->fresh());
        $this->dispatch('toast', message: __('griglia::t.'.$field.'_set_task', [
            'title' => $todo->title,
            $field => $effective ? ($catalogue[$effective] ?? $effective) : self::presetDefaultLabel($field, Agent::effective($todo->fresh()), $catalogue),
        ]));
    }

    /**
     * How the toast names «nothing chosen»: the value the agent CLI starts with when it is declared
     * (config agent_default_model/effort), «CLI default» when it is not (task 659).
     */
    private static function presetDefaultLabel(string $field, string $agentKey, array $catalogue): string
    {
        $default = $field === 'model' ? Agent::defaultModel($agentKey) : Agent::defaultEffort($agentKey);

        return $default
            ? __('griglia::t.preset_default', ['value' => $catalogue[$default] ?? $default])
            : __('griglia::t.preset_cli_default');
    }

    /* ---------- Plan mode: start / status ---------- */

    /** Plan status of the current list: null if not a plan, else [next id|null, done, total, running]. */
    protected function planStatus(): ?array
    {
        $chained = $this->active()->whereNotNull('depends_on_id')->exists();
        $list = Checklist::find(Checklist::currentId());
        if (! $chained && ! ($list?->plan_prompt)) {
            return null;
        }
        $todos = $this->active()->orderBy('order')->get(['id', 'completed', 'open_to_work', 'working', 'paused', 'question']);
        $next = $todos->first(fn ($t) => ! $t->completed && ! $t->open_to_work && ! $t->working && ! $t->paused && ! $t->question);
        $running = $todos->contains(fn ($t) => ! $t->completed && ($t->open_to_work || $t->working || $t->paused || $t->question));

        return ['next' => $next?->id, 'done' => $todos->where('completed', true)->count(), 'total' => $todos->count(), 'running' => $running, 'paused' => (bool) $list?->plan_paused];
    }

    /** Pause the plan: open tasks go back to waiting ⚪, the chain stops opening the next ones (a working task is left to the agent). */
    public function pausePlan(): void
    {
        if (! $this->planStatus()) {
            return;
        }
        $list = Checklist::find(Checklist::currentId());
        $list?->update(['plan_paused' => true]);
        $this->active()->where('completed', false)->where('open_to_work', true)->where('working', false)->update(['open_to_work' => false]);
        $this->dispatch('toast', message: __('griglia::t.plan.paused'), type: 'info');
    }

    /** Start (or resume) the plan: the first not-started task becomes open to work 🟢; the chain does the rest. */
    public function startPlan(): void
    {
        $status = $this->planStatus();
        if (! $status) {
            return;
        }
        Checklist::whereKey(Checklist::currentId())->update(['plan_paused' => false]);
        if (! $status['next']) {
            return;
        }
        $todo = $this->active()->findOrFail($status['next']);
        $todo->update(['open_to_work' => true, 'stopped_at' => null]);
        $this->dispatch('toast', message: __('griglia::t.plan.started', ['title' => $todo->title]), type: 'success');
    }

    protected function archivedCount(): int
    {
        return $this->scoped()->whereNotNull('archived_at')->count();
    }

    public function toggleArchived(): void
    {
        $this->showArchived = ! $this->showArchived;
        $this->cancelInsert();
        $this->closeEdit(); // what was being typed is already saved: nothing gets thrown away
    }

    public function archive(int $todoId): void
    {
        $todo = $this->active()->findOrFail($todoId);
        if ($todo->working) {
            return;
        }
        $todo->update(['archived_at' => now()]);

        // Close the gap in the numbering of the active todos
        $this->active()->where('order', '>', $todo->order)->decrement('order');
        $this->dispatch('toast', message: __('griglia::t.msg.archived', ['title' => $todo->title]), type: 'info');
    }

    public function unarchive(int $todoId): void
    {
        $todo = $this->scoped()->whereNotNull('archived_at')->findOrFail($todoId);
        $todo->update(['archived_at' => null, 'order' => ((int) $this->active()->max('order')) + 1]);
        $this->dispatch('toast', message: __('griglia::t.msg.restored', ['title' => $todo->title]));
    }

    /** Name of the current list: it is the title of every page. */
    protected function listName(): string
    {
        return Checklist::findOrFail(Checklist::currentId())->name;
    }

    public function toggle(int $todoId): void
    {
        $todo = $this->scoped()->findOrFail($todoId);
        if ($todo->working) {
            return;
        }

        // A closed task stays closed: reopening it would put back in front of the agent something it had
        // already answered. To carry on, «resume» makes a new task linked to this one (task 348).
        if ($todo->completed) {
            $this->dispatch('toast', message: __('griglia::t.msg.done_is_done'), type: 'info');

            return;
        }

        $todo->update(['completed' => true]);

        $this->dispatch('toast', message: __('griglia::t.msg.completed', ['title' => $todo->title]), type: 'success');
    }

    public function toggleOpenToWork(int $todoId): void
    {
        $todo = $this->scoped()->findOrFail($todoId);

        // Done is done: the dot of a closed task does not put it back to work (task 348).
        if ($todo->completed) {
            $this->dispatch('toast', message: __('griglia::t.msg.done_is_done'), type: 'info');

            return;
        }

        // With open questions the dot leads to the modal to answer them
        if ($todo->question) {
            $this->dispatch('open-ingredients', todoId: $todo->id);

            return;
        }

        // Paused by the agent: the user explicitly reopens it for the persistent worker.
        if ($todo->paused) {
            $todo->update(['paused' => false, 'open_to_work' => true, 'stopped_at' => null]);
            $this->dispatch('toast', message: __('griglia::t.msg.otw_on', ['title' => $todo->title]), type: 'success');

            return;
        }

        // Working (🔧): the click stops the assistant → back to ⚪, with a trace left in stopped_at
        if ($todo->working) {
            $todo->update(['working' => false, 'open_to_work' => false, 'stopped_at' => now()]);
            $this->dispatch('toast', message: __('griglia::t.msg.stopped', ['title' => $todo->title]), type: 'info');

            return;
        }

        $todo->open_to_work = ! $todo->open_to_work;
        if ($todo->open_to_work) {
            $todo->stopped_at = null; // back to 🟢: the stop does not apply any more
        }
        $todo->save();

        $this->dispatch('toast', message: __($todo->open_to_work ? 'griglia::t.msg.otw_on' : 'griglia::t.msg.otw_off', ['title' => $todo->title]), type: $todo->open_to_work ? 'success' : 'info');
    }

    /**
     * «Resume»: from a completed todo it opens a new linked todo (parent_id) right after it,
     * same title, empty note to fill in with additions/changes; the context of the old one
     * (note, comment, sub-tasks, images) stays readable from the new one.
     */
    public function resume(int $todoId): void
    {
        $old = $this->scoped()->findOrFail($todoId);

        if (! $old->completed) {
            $this->dispatch('toast', message: __('griglia::t.msg.resume_only_done'), type: 'error');

            return;
        }

        // Position: right after the original when that is active, otherwise at the end
        $position = $old->archived_at ? ((int) $this->active()->max('order') + 1) : $old->order + 1;
        $this->active()->where('order', '>=', $position)->increment('order');

        $new = Todo::create([
            'title' => $old->title,
            'order' => $position,
            'completed' => false,
            'checklist_id' => $old->checklist_id,
            'parent_id' => $old->id,
        ]);

        if ($this->showArchived) {
            $this->showArchived = false;
        }

        $this->dispatch('toast', message: __('griglia::t.msg.resumed'));
        $this->dispatch('open-ingredients', todoId: $new->id);
    }

    public function startEdit(int $todoId): void
    {
        $todo = $this->scoped()->findOrFail($todoId);
        if ($todo->working) {
            return;
        }
        $this->editingId = $todo->id;
        $this->titleDraft = $todo->title;
        $this->titleOriginal = $todo->title;
    }

    /**
     * «Undo»: puts the title back as it was when the edit started.
     * The rename stays open — it is a step back, not a close (task 438).
     */
    public function revertEdit(): void
    {
        if (! $this->editingId || $this->titleOriginal === null) {
            return;
        }

        $this->scoped()->whereKey($this->editingId)->where('title', '!=', $this->titleOriginal)
            ->update(['title' => $this->titleOriginal]);

        $this->titleDraft = $this->titleOriginal;
        $this->dispatch('toast', message: __('griglia::t.msg.reverted'));
    }

    /** Close the rename without touching what has already been saved. */
    protected function closeEdit(): void
    {
        $this->editingId = null;
        $this->titleDraft = '';
        $this->titleOriginal = null;
    }

    /** Live save: the draft comes from the field (wire:model.live) at every pause in the typing. */
    public function updatedTitleDraft(): void
    {
        $this->autosaveEdit();
    }

    /** Persist the draft without closing the rename and without a toast (there would be one per pause). */
    protected function autosaveEdit(): bool
    {
        $title = trim($this->titleDraft);

        if ($title === '' || ! $this->editingId || ! $this->titleFits($title)) {
            return false;
        }

        $saved = $this->scoped()->whereKey($this->editingId)->where('working', false)->where('title', '!=', $title)
            ->update(['title' => $title]) > 0;

        if ($saved) {
            $this->dispatch('griglia-autosaved'); // the «saved» light next to the field
        }

        return $saved;
    }

    /**
     * Close the rename without buttons: Enter, Esc or a click outside the field (task 438). What is
     * written is already saved; with an empty or too long title we stay inside, otherwise the text
     * just typed would vanish without ever having been saved.
     */
    public function finishEdit(): void
    {
        $title = trim($this->titleDraft);

        if ($title === '' || ! $this->editingId || ! $this->titleFits($title)) {
            return;
        }

        $this->autosaveEdit();
        $this->closeEdit();
    }

    /** Title within the limit? Otherwise warn and do not save (no silent truncation). */
    protected function titleFits(string $title): bool
    {
        if (mb_strlen($title) <= self::titleMax()) {
            return true;
        }

        $this->dispatch('toast', message: __('griglia::t.msg.title_too_long', ['max' => self::titleMax(), 'n' => mb_strlen($title)]), type: 'error');

        return false;
    }

    public function startInsert(int $position): void
    {
        $this->insertAt = $position;
        $this->newTitle = '';
    }

    public function cancelInsert(): void
    {
        $this->insertAt = null;
        $this->newTitle = '';
    }

    public function saveInsert(): void
    {
        $title = trim($this->newTitle);

        if ($title === '' || $this->insertAt === null || ! $this->titleFits($title)) {
            return;
        }

        // Make room: every todo of the list from that position on shifts by one.
        $this->active()->where('order', '>=', $this->insertAt)->increment('order');

        Todo::create([
            'title' => $title,
            'order' => $this->insertAt,
            'completed' => false,
            'checklist_id' => Checklist::currentId(),
        ]);

        $this->cancelInsert();
        $this->dispatch('toast', message: __('griglia::t.msg.added', ['title' => $title]));
    }

    /** @param array<int, int|string> $orderedIds Ids of the todos in the order shown after the drag. */
    public function reorder(array $orderedIds): void
    {
        if ($this->showArchived || $this->isFiltering()) {
            return;
        }

        foreach ($orderedIds as $index => $id) {
            $this->active()->whereKey($id)->update(['order' => $index + 1]);
        }
        $this->rechainPlan();
    }

    /** Plan lists: the chain follows the list order — each task depends on the previous one (by `order`). */
    protected function rechainPlan(): void
    {
        if (! $this->planStatus()) {
            return;
        }
        $prev = null;
        foreach ($this->active()->orderBy('order')->orderBy('id')->get(['id', 'depends_on_id']) as $t) {
            $dep = $prev?->id;
            if ((int) $t->depends_on_id !== (int) $dep) {
                Todo::whereKey($t->id)->update(['depends_on_id' => $dep]);
            }
            $prev = $t;
        }
    }

    public function delete(int $todoId): void
    {
        $todo = $this->scoped()->findOrFail($todoId);
        if ($todo->working) {
            return;
        }
        // Resume chain: whoever was «resumed» from this one moves to the grandparent, so the history does not break (task 416)
        Todo::where('parent_id', $todo->id)->update(['parent_id' => $todo->parent_id]);
        $todo->delete();

        // Close the gap left by the deleted item (only if it was active: archived ones have no place in the numbering)
        if (! $todo->archived_at) {
            $this->active()->where('order', '>', $todo->order)->decrement('order');
        }
        $this->dispatch('toast', message: __('griglia::t.msg.deleted', ['title' => $todo->title]), type: 'info');
    }

    /** Owner of the lists: identifies the private Reverb channel the updates arrive on. */
    public int $userId = 0;

    public function boot(): void
    {
        // In boot() (not mount): subclasses with mount($theme) would stay incompatible
        $this->userId = (int) auth()->id();
    }

    /**
     * Live update: a todo of the list changed elsewhere (artisan command of the
     * assistant, another device). When it concerns the current list, list and modal are
     * re-rendered; when the status was changed from the console, a toast says so.
     */
    /** Listeners: the private broadcast channel comes from config (griglia.broadcast_channel). */
    protected function getListeners(): array
    {

        return [
            Mode::echoListener() => 'onTodoChanged',
            'live-resync' => 'resync',
            'ingredients-updated' => 'refreshList',
            'resume-todo' => 'resume',
            'cmd-archive' => 'archive',
            'cmd-delete' => 'delete',
        ];
    }

    public function onTodoChanged(array $event = []): void
    {
        if ((int) ($event['checklist_id'] ?? 0) !== Checklist::currentId()) {
            return;
        }

        $this->dispatch('todo-changed-live'); // the modal, if open, refreshes

        if (($event['source'] ?? '') === 'cli' && ! empty($event['state_changed']) && app(AppSettings::class)->toast_console_changes) {
            $title = (string) ($event['title'] ?? '');
            [$key, $type] = match ($event['state'] ?? '') {
                'working' => ['agent_working', 'info'],
                'done' => ['agent_done', 'success'],
                'question' => ['agent_question', 'info'],
                default => ['agent_updated', 'info'],
            };
            $message = __('griglia::t.msg.'.$key, ['title' => $title]);
            $this->dispatch('toast', message: $message, type: $type);
        }
    }

    /** Re-sync after background/reconnection (see resources/js/echo.js): a re-render is enough. */
    public function resync(): void
    {
        $this->dispatch('todo-changed-live');
    }

    public function refreshList(): void
    {
        // Empty on purpose: the event forces the list to re-render
        // so the sub-task counters stay aligned with the modal.
    }

    public function render()
    {
        // Default: the generic themed view with the default theme (dedicated styles override render())
        $t = Themes::get(Themes::default());
        $list = Checklist::find(Checklist::currentId());

        return view('griglia::livewire.todo-list', [
            'todos' => $this->todos(),
            't' => $t,
            'theme' => Themes::default(),
            'listName' => $this->listName(),
            'archivedCount' => $this->archivedCount(),
            'filtering' => $this->isFiltering(),
            'plan' => $this->planStatus(),
            'listAgent' => (string) ($list?->agent ?? ''),
            'listModel' => (string) ($list?->model ?? ''),
            'listEffort' => (string) ($list?->effort ?? ''),
        ])->layout('griglia::layouts.themed', ['theme' => Themes::default()])->title($this->listName());
    }
}
