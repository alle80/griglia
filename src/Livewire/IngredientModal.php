<?php

namespace Alle80\Griglia\Livewire;

use Alle80\Griglia\Agent;
use Alle80\Griglia\Models\Attachment;
use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Models\Ingredient;
use Alle80\Griglia\Models\Question;
use Alle80\Griglia\Models\Todo;
use Alle80\Griglia\Support\ImageDescription;
use Alle80\Griglia\Support\ImageStore;
use Alle80\Griglia\Support\Live;
use Alle80\Griglia\Support\Skills;
use Alle80\Griglia\Themes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

class IngredientModal extends Component
{
    use WithFileUploads;

    public bool $open = false;

    /** Incoming images (upload from a file, the camera or a paste). */
    public array $images = [];

    public ?string $imageError = null;

    public ?int $todoId = null;

    public string $newIngredient = '';

    /** Draft of the note while it is being edited (null = not editing). */
    public ?string $notesDraft = null;

    /** Draft of the title while it is being renamed from the modal (null = not editing). */
    public ?string $titleDraft = null;

    /**
     * Values as they were when the edit opened: saving is live (at every pause in the typing),
     * so «undo» is no longer enough to drop the draft — it has to put these back.
     */
    public ?string $titleOriginal = null;

    public ?string $notesOriginal = null;

    /** Ingredient being renamed and its draft. */
    public ?int $editingIngredientId = null;

    public string $ingredientDraft = '';

    /** Todos the user can reach: only those of their own lists. */
    protected function reachable(): Builder
    {
        return Todo::whereIn('checklist_id', Checklist::mine()->select('id'));
    }

    /** The todo open in the modal (null when closed or no longer reachable). */
    protected function todo(): ?Todo
    {
        return $this->todoId ? $this->reachable()->with([
            'checklist:id,name,agent', 'ingredients', 'attachments', 'questions', 'parent.ingredients',
            'reviewOf:id,title,agent,reviewer_agent,review_status',
            'reviewAttempts:id,title,agent,review_of_id,review_round,review_outcome,completed',
        ])->find($this->todoId) : null;
    }

    /**
     * A completed or working item is read-only: notes, questions and sub-tasks are untouchable
     * until it goes back to an earlier state. Returns the todo only when it is editable.
     */
    protected function editable(): ?Todo
    {
        $todo = $this->todo();

        if ($todo && ($todo->completed || $todo->working)) {
            $this->dispatch('toast', message: __('griglia::t.msg.readonly'), type: 'info');

            return null;
        }

        return $todo;
    }

    /**
     * "New task" button: create a blank todo in the current list and open it in title editing.
     * Done in the modal (via a client dispatch) so it reliably opens — a server-side dispatch from
     * the list to this child component gets lost when the list re-renders after creating the todo.
     */
    #[On('open-new-task')]
    public function createNew(?int $position = null): void
    {
        $listId = Checklist::currentId();
        if (! $listId) {
            return;
        }

        $active = Todo::where('checklist_id', $listId)->whereNull('archived_at');
        $end = ((int) (clone $active)->max('order')) + 1;
        $order = $position !== null ? max(1, min($end, $position)) : $end;
        if ($order < $end) {
            (clone $active)->where('order', '>=', $order)->increment('order'); // the «+» between rows: make room here
        }

        $todo = Todo::create([
            'title' => '',
            'order' => $order,
            'completed' => false,
            'checklist_id' => $listId,
        ]);

        // Plan lists: keep the chain = list order (the task after the inserted one now depends on it)
        if ($order < $end) {
            $prev = null;
            foreach (Todo::where('checklist_id', $listId)->whereNull('archived_at')->orderBy('order')->orderBy('id')->get(['id', 'depends_on_id']) as $t) {
                $dep = $prev?->id;
                if ($prev !== null && (int) $t->depends_on_id !== (int) $dep && Todo::where('checklist_id', $listId)->whereNotNull('depends_on_id')->exists()) {
                    Todo::whereKey($t->id)->update(['depends_on_id' => $dep]);
                }
                $prev = $t;
            }
        }

        $this->dispatch('ingredients-updated'); // the list shows the new (empty) row
        $this->openFor($todo->id, true);
    }

    #[On('open-ingredients')]
    public function openFor(int $todoId, bool $edit = false): void
    {
        $this->reachable()->findOrFail($todoId);

        $this->todoId = $todoId;
        $this->resetDrafts();
        $this->answers = Question::where('todo_id', $todoId)->pluck('answer', 'id')->map(fn ($v) => (string) $v)->all();
        $this->open = true;

        // A brand-new task (created blank by "add") opens straight into title editing.
        if ($edit) {
            $this->titleDraft = (string) ($this->todo()?->title ?? '');
        }

        // Opening a completed task marks the agent's result as seen (removes the highlight).
        $todo = $this->todo();
        if ($todo && $todo->completed && ! $todo->result_seen) {
            $todo->update(['result_seen' => true]);
            Live::todoChanged($todo);
        }
    }

    /** Live update (Reverb) received from the list: the open modal re-renders. */
    #[On('todo-changed-live')]
    public function refreshLive(): void
    {
        // If the open todo does not exist any more, close
        if ($this->open && ! $this->todo()) {
            $this->close();
        }
    }

    public function close(): void
    {
        // Abandoned new task (created blank by "add" and never titled) → drop it on close.
        $todo = $this->todo();
        if ($todo && trim((string) $todo->title) === ''
            && trim((string) $todo->notes) === ''
            && $todo->ingredients()->count() === 0
            && $todo->attachments()->count() === 0) {
            $todo->forceDelete(); // blank and untouched: no stats to keep, no need to clog the trash
            $this->dispatch('ingredients-updated');
        }

        $this->open = false;
        $this->todoId = null;
        $this->resetDrafts();
    }

    protected function resetDrafts(): void
    {
        $this->newIngredient = '';
        $this->notesDraft = null;
        $this->titleDraft = null;
        $this->titleOriginal = null;
        $this->notesOriginal = null;
        $this->editingIngredientId = null;
        $this->ingredientDraft = '';
        $this->images = [];
        $this->imageError = null;
        $this->answers = [];
    }

    // ----- Images -----

    /** As soon as Livewire has received the files (from <input type=file> or from a paste), save them. */
    public function updatedImages(): void
    {
        $this->imageError = null;

        if (! ($todo = $this->todo())) {
            $this->images = [];

            return;
        }

        try {
            $this->validate([
                'images.*' => ['image', 'mimes:jpeg,jpg,png,gif', 'max:20480'],
            ], [
                'images.*.image' => __('griglia::t.msg.not_an_image'),
                'images.*.mimes' => __('griglia::t.msg.image_formats'),
                'images.*.max' => __('griglia::t.msg.image_too_big'),
            ]);
        } catch (ValidationException $e) {
            $this->imageError = collect($e->errors())->flatten()->first();
            $this->images = [];
            $this->dispatch('toast', message: $this->imageError, type: 'error');

            return;
        }

        $saved = 0;

        try {
            foreach ($this->images as $file) {
                $attachment = ImageStore::store($todo, $file);
                $saved++;

                // AI description for the search: after the answer, so the upload stays fast
                if (ImageDescription::enabled()) {
                    dispatch(fn () => ImageDescription::describe($attachment->fresh()))->afterResponse();
                }
            }
        } catch (RuntimeException $e) {
            $this->imageError = $e->getMessage();
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }

        $this->images = [];
        $this->dispatch('ingredients-updated');

        if ($saved > 0) {
            $this->dispatch('toast', message: $saved === 1 ? __('griglia::t.msg.image_uploaded') : __('griglia::t.msg.images_uploaded', ['count' => $saved]));
        }
    }

    public function deleteAttachment(int $attachmentId): void
    {
        if (! $this->todo()) {
            return;
        }

        Attachment::where('todo_id', $this->todoId)->whereKey($attachmentId)->first()?->delete();

        $this->dispatch('ingredients-updated');
        $this->dispatch('toast', message: __('griglia::t.msg.image_deleted'), type: 'info');
    }

    // ----- Questions of the assistant -----

    /** Draft answers, indexed by question id. */
    public array $answers = [];

    public function saveAnswer(int $questionId): void
    {
        if (! $this->editable()) {
            return;
        }

        $q = Question::where('todo_id', $this->todoId)->findOrFail($questionId);
        $answer = trim($this->answers[$questionId] ?? '');
        $q->answer = $answer === '' ? null : $answer;
        $q->save();

        $this->dispatch('toast', message: __($q->answer ? 'griglia::t.msg.answer_saved' : 'griglia::t.msg.answer_removed'), type: $q->answer ? 'success' : 'info');
    }

    public function selectAnswer(int $questionId, string $answer): void
    {
        if (! $this->editable()) {
            return;
        }

        $q = Question::where('todo_id', $this->todoId)->findOrFail($questionId);
        abort_unless(in_array($answer, $q->choices ?? [], true), 422, __('griglia::t.errors.invalid_request'));
        $this->answers[$questionId] = $answer;
        $this->saveAnswer($questionId);
    }

    /** Last step: every question has an answer → the item goes back to "open to work". */
    public function resumeWork(): void
    {
        if (! ($todo = $this->editable())) {
            return;
        }

        if ($todo->questions()->whereNull('answer')->exists()) {
            $this->dispatch('toast', message: __('griglia::t.msg.answer_all_first'), type: 'error');

            return;
        }

        $todo->update(['question' => false, 'open_to_work' => true, 'working' => false]);

        $this->dispatch('ingredients-updated');
        $this->dispatch('toast', message: __('griglia::t.msg.restarted', ['title' => $todo->title]));
    }

    /** «Resume» from the modal: the logic (position, scoping) lives in TodoList::resume. */
    public function resumeTodo(): void
    {
        if (! ($todo = $this->todo())) {
            return;
        }

        $this->dispatch('resume-todo', todoId: $todo->id);
    }

    // ----- Commands in the header -----

    /**
     * Ids of the active tasks of the list, in the order they are shown: the modal walks them with the
     * arrows, which is how you follow a plan from one task to the next (task 365).
     *
     * @return array<int, int>
     */
    public function siblingIds(): array
    {
        $todo = $this->todo();

        if (! $todo) {
            return [];
        }

        return Todo::where('checklist_id', $todo->checklist_id)
            ->whereNull('archived_at')
            ->orderBy('order')->orderBy('id')
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Position of the open task among its siblings, 1-based (0 when it is not in the list). */
    public function position(): int
    {
        $todo = $this->todo();
        $i = $todo ? array_search((int) $todo->id, $this->siblingIds(), true) : false;

        return $i === false ? 0 : $i + 1;
    }

    /** Id of the previous (-1) or next (+1) task, or null at the ends. */
    public function siblingId(int $delta): ?int
    {
        $ids = $this->siblingIds();
        $position = $this->position();

        return $position === 0 ? null : ($ids[$position - 1 + $delta] ?? null);
    }

    /** Open the previous (-1) or next (+1) task without leaving the modal. */
    public function goSibling(int $delta): void
    {
        if ($id = $this->siblingId($delta === -1 ? -1 : 1)) {
            $this->openFor($id);
        }
    }

    /** State of the current todo, for the coloured badge: waiting|open|working|paused|question|done. */
    public function stateKey(): string
    {
        $todo = $this->todo();

        return match (true) {
            ! $todo => 'waiting',
            (bool) $todo->completed => 'done',
            (bool) $todo->question => 'question',
            (bool) $todo->paused => 'paused',
            (bool) $todo->working => 'working',
            (bool) $todo->open_to_work => 'open',
            default => 'waiting',
        };
    }

    /** Toggle open-to-work / stop, mirroring the row dot (no archived/order changes). */
    public function toggleOpenToWork(): void
    {
        if (! ($todo = $this->editable())) {
            return;
        }

        if ($todo->paused) {
            $todo->update(['paused' => false, 'open_to_work' => true, 'stopped_at' => null]);
            $this->dispatch('toast', message: __('griglia::t.msg.otw_on', ['title' => $todo->title]), type: 'success');
        } elseif ($todo->working) {
            $todo->update(['working' => false, 'open_to_work' => false, 'stopped_at' => now()]);
            $this->dispatch('toast', message: __('griglia::t.msg.stopped', ['title' => $todo->title]), type: 'info');
        } else {
            $todo->open_to_work = ! $todo->open_to_work;
            if ($todo->open_to_work) {
                $todo->stopped_at = null;
            }
            $todo->save();
            $this->dispatch('toast', message: __($todo->open_to_work ? 'griglia::t.msg.otw_on' : 'griglia::t.msg.otw_off', ['title' => $todo->title]), type: $todo->open_to_work ? 'success' : 'info');
        }

        Live::todoChanged($todo);
        $this->dispatch('ingredients-updated');
    }

    /**
     * Set the state from the badge in the modal header: 'waiting' ⚪, 'open' 🟢 or 'done' ✔
     * (the agent states working/question are left to the agent; choosing a state while the agent works = stop).
     */
    public function setState(string $state): void
    {
        $todo = $this->todo();
        if (! $todo || ! in_array($state, ['waiting', 'open', 'done'], true)) {
            return;
        }

        // A closed task stays closed: carry on with «resume», which makes a new task linked to it (task 348).
        if ($todo->completed && $state !== 'done') {
            $this->dispatch('toast', message: __('griglia::t.msg.done_is_done'), type: 'info');

            return;
        }
        $wasWorking = $todo->working;
        $attrs = match ($state) {
            'waiting' => ['completed' => false, 'open_to_work' => false, 'working' => false, 'paused' => false, 'question' => false, 'outcome' => null],
            'open' => ['completed' => false, 'open_to_work' => true, 'working' => false, 'paused' => false, 'question' => false, 'stopped_at' => null, 'outcome' => null],
            // closed by the user: there is no agent result to flag, so no outcome
            'done' => ['completed' => true, 'open_to_work' => false, 'working' => false, 'paused' => false, 'question' => false, 'result_seen' => true, 'progress' => null, 'outcome' => null],
        };
        if ($wasWorking && $state !== 'open') {
            $attrs['stopped_at'] = now();
        }
        $todo->update($attrs);
        $this->dispatch('toast', message: __('griglia::t.msg.state_set', ['state' => __('griglia::t.state.'.$state), 'title' => $todo->title]), type: $state === 'done' ? 'success' : 'info');
        Live::todoChanged($todo);
        $this->dispatch('ingredients-updated');
    }

    /** Multi-agent: choose which agent handles this task ('' = the list's default). */
    public function setAgent(string $agent): void
    {
        $todo = $this->todo();
        if (! $todo || $todo->working) {
            return;
        }
        $agent = trim($agent);
        if ($agent !== '' && ! array_key_exists($agent, Agent::all())) {
            return;
        }
        $todo->update(['agent' => $agent ?: null]);
        $this->dispatch('ingredients-updated');
    }

    public function setReviewer(string $agent): void
    {
        $todo = $this->todo();
        if (! $todo || $todo->working || $todo->completed || $todo->review_status || $todo->isReviewAttempt()) {
            return;
        }
        $agent = trim($agent);
        if ($agent !== '' && (! array_key_exists($agent, Agent::all()) || $agent === Agent::effective($todo))) {
            return;
        }
        $todo->update(['reviewer_agent' => $agent ?: null]);
        $this->dispatch('ingredients-updated');
    }

    /** Move the todo to another list of the user (appended at the end of the active items there). */
    public function moveTo(int $checklistId): void
    {
        $todo = $this->todo();
        if (! $todo || $todo->working || $checklistId === (int) $todo->checklist_id || ! Checklist::mine()->whereKey($checklistId)->exists()) {
            return;
        }
        $from = $todo->checklist_id;
        $order = $todo->order;
        $newOrder = ((int) Todo::where('checklist_id', $checklistId)->whereNull('archived_at')->max('order')) + 1;
        $todo->update(['checklist_id' => $checklistId, 'order' => $todo->archived_at ? 0 : $newOrder]);
        if (! $todo->archived_at) {
            Todo::where('checklist_id', $from)->whereNull('archived_at')->where('order', '>', $order)->decrement('order'); // close the gap
        }
        $target = Checklist::find($checklistId);
        $this->dispatch('toast', message: __('griglia::t.msg.moved', ['title' => $todo->title, 'list' => $target?->name]));
        $this->dispatch('ingredients-updated');
        $this->close();
    }

    /** Archive / delete reuse the list logic (order reindex) then close the modal. */
    public function archiveTodo(): void
    {
        if (($todo = $this->todo()) && ! $todo->working) {
            $this->dispatch('cmd-archive', todoId: $todo->id);
            $this->close();
        }
    }

    public function deleteTodo(): void
    {
        if (($todo = $this->todo()) && ! $todo->working) {
            $this->dispatch('cmd-delete', todoId: $todo->id);
            $this->close();
        }
    }

    // ----- Title -----

    public function editTitle(): void
    {
        if (! ($todo = $this->editable())) {
            return;
        }

        $this->titleDraft = $todo->title;
        $this->titleOriginal = $todo->title;
    }

    /**
     * «Undo»: puts the title back as it was when the edit started.
     * The field stays open — it is a step back, not a close (task 438).
     */
    public function revertTitle(): void
    {
        if (! ($todo = $this->editable()) || $this->titleOriginal === null) {
            return;
        }

        if ($todo->title !== $this->titleOriginal) {
            $todo->update(['title' => $this->titleOriginal]);
            $this->dispatch('ingredients-updated');
        }

        $this->titleDraft = $this->titleOriginal;
        $this->dispatch('toast', message: __('griglia::t.msg.reverted'));
    }

    /** Live save: the draft comes from the field (wire:model.live) at every pause in the typing. */
    public function updatedTitleDraft(): void
    {
        $this->autosaveTitle();
    }

    /**
     * Persist the title draft without closing the edit and without a toast (there would be one per pause):
     * the «saved» light next to the field is enough. Returns false when there was nothing to save.
     */
    protected function autosaveTitle(): bool
    {
        if (! ($todo = $this->editable()) || $this->titleDraft === null) {
            return false;
        }

        $title = trim($this->titleDraft);

        if ($title === '' || $title === $todo->title) {
            return false;
        }

        if (mb_strlen($title) > TodoList::titleMax()) {
            $this->dispatch('toast', message: __('griglia::t.msg.title_too_long', ['max' => TodoList::titleMax(), 'n' => mb_strlen($title)]), type: 'error');

            return false;
        }

        $todo->update(['title' => $title]);
        $this->dispatch('ingredients-updated'); // the list shows the new title
        $this->dispatch('griglia-autosaved'); // the «saved» light next to the field

        return true;
    }

    /**
     * Close the title edit without buttons: Enter, Esc or a click outside the field (task 438).
     * What is written is already saved.
     */
    public function finishTitle(): void
    {
        if (! $this->editable() || $this->titleDraft === null) {
            return;
        }

        $this->autosaveTitle();
        $title = trim($this->titleDraft);

        if ($title === '' || mb_strlen($title) > TodoList::titleMax()) {
            return; // invalid title: stay in edit mode, the autosave already warned about it
        }

        $this->titleDraft = null;
        $this->titleOriginal = null;
    }

    // ----- Note -----

    public function editNotes(): void
    {
        if (! ($todo = $this->editable())) {
            return;
        }

        $this->notesDraft = $todo->notes ?? '';
        $this->notesOriginal = $todo->notes ?? '';
    }

    /**
     * «Undo»: puts the note back as it was when the edit started.
     * The editor stays open — it is a step back, not a close (task 438).
     */
    public function revertNotes(): void
    {
        if (! ($todo = $this->editable()) || $this->notesOriginal === null) {
            return;
        }

        if ((string) $todo->notes !== $this->notesOriginal) {
            $todo->notes = $this->notesOriginal === '' ? null : $this->notesOriginal;
            $todo->save();
            $this->dispatch('ingredients-updated');
        }

        $this->notesDraft = $this->notesOriginal;
        $this->dispatch('toast', message: __('griglia::t.msg.reverted'));
    }

    /** Live save: the draft comes from the editor (wire:model.live) at every pause in the typing. */
    public function updatedNotesDraft(): void
    {
        $this->autosaveNotes();
    }

    /** Persist the note draft without closing the editor and without a toast. */
    protected function autosaveNotes(): bool
    {
        if (! ($todo = $this->editable()) || $this->notesDraft === null) {
            return false;
        }

        $notes = trim($this->notesDraft);
        $notes = $notes === '' ? null : $notes;

        if ($notes === $todo->notes) {
            return false;
        }

        $todo->notes = $notes;
        $todo->save();
        $this->dispatch('ingredients-updated');
        $this->dispatch('griglia-autosaved'); // the «saved» light next to the editor

        return true;
    }

    /**
     * Close the note editor without buttons: Esc or a click outside (task 438). What is written
     * is already saved.
     */
    public function finishNotes(): void
    {
        if (! $this->editable() || $this->notesDraft === null) {
            return;
        }

        $this->autosaveNotes();
        $this->notesDraft = null;
        $this->notesOriginal = null;
    }

    // ----- Skills of the agent chosen for this task -----

    /** Toggle a skill (from the catalogue, or already chosen) on the open todo. */
    public function toggleSkill(string $name): void
    {
        if (! ($todo = $this->editable())) {
            return;
        }
        $name = trim($name);
        $chosen = array_values((array) $todo->skills);
        // Only a skill the agent of this task can really invoke; an already chosen one stays togglable, so a
        // leftover from another agent can still be removed
        if (! in_array($name, $chosen, true) && ! isset($this->skillCatalogue($todo)[$name])) {
            return; // unknown skill, or not available to this agent
        }
        $chosen = in_array($name, $chosen, true) ? array_values(array_diff($chosen, [$name])) : [...$chosen, $name];
        $todo->skills = $chosen ?: null;
        $todo->save();
        $this->dispatch('ingredients-updated');
    }

    // ----- Rename an ingredient -----

    public function editIngredient(int $ingredientId): void
    {
        if (! $this->editable()) {
            return;
        }

        $ingredient = Ingredient::where('todo_id', $this->todoId)->findOrFail($ingredientId);
        $this->editingIngredientId = $ingredient->id;
        $this->ingredientDraft = $ingredient->name;
    }

    public function cancelEditIngredient(): void
    {
        $this->editingIngredientId = null;
        $this->ingredientDraft = '';
    }

    public function saveIngredient(): void
    {
        $name = trim($this->ingredientDraft);

        if ($name === '' || ! $this->editingIngredientId || ! $this->editable()) {
            return;
        }

        Ingredient::where('todo_id', $this->todoId)->whereKey($this->editingIngredientId)->first()?->update(['name' => $name]);

        $this->cancelEditIngredient();
        $this->dispatch('ingredients-updated');
        $this->dispatch('toast', message: __('griglia::t.msg.subtask_renamed'));
    }

    public function addIngredient(): void
    {
        $name = trim($this->newIngredient);

        if ($name === '' || ! $this->editable()) {
            return;
        }

        Ingredient::create([
            'todo_id' => $this->todoId,
            'name' => $name,
            'checked' => false,
            'order' => (Ingredient::where('todo_id', $this->todoId)->max('order') ?? 0) + 1,
        ]);

        $this->newIngredient = '';
        $this->dispatch('ingredients-updated');
        $this->dispatch('toast', message: __('griglia::t.msg.subtask_added'));
    }

    /** @param array<int, int|string> $orderedIds Ids of the sub-tasks in the order shown after the drag. */
    public function reorderIngredients(array $orderedIds): void
    {
        if (! $this->editable()) {
            return;
        }

        foreach ($orderedIds as $index => $id) {
            Ingredient::where('todo_id', $this->todoId)->whereKey($id)->update(['order' => $index + 1]);
        }
    }

    public function deleteIngredient(int $ingredientId): void
    {
        if (! $this->editable()) {
            return;
        }

        Ingredient::where('todo_id', $this->todoId)->whereKey($ingredientId)->first()?->delete();

        $this->dispatch('ingredients-updated');
        $this->dispatch('toast', message: __('griglia::t.msg.subtask_deleted'), type: 'info');
    }

    public function toggleIngredient(int $ingredientId): void
    {
        if (! $this->editable()) {
            return;
        }

        $ingredient = Ingredient::where('todo_id', $this->todoId)->findOrFail($ingredientId);
        $ingredient->checked = ! $ingredient->checked;
        $ingredient->save();

        $this->dispatch('ingredients-updated');
    }

    public function render()
    {
        // Default view: the generic themed modal with the default theme (dedicated styles override render())
        return view('griglia::livewire.ingredient-modal', $this->viewData() + ['t' => Themes::get(Themes::default())]);
    }

    /** The skills the agent assigned to this task can actually invoke (task 375). */
    protected function skillCatalogue(Todo $todo): array
    {
        return Skills::forAgent(Agent::effective($todo));
    }

    /** Data shared by every view of the modal (base one and dedicated styles). */
    protected function viewData(): array
    {
        $todo = $this->todo();

        return [
            'todo' => $todo,
            'readonly' => (bool) ($todo?->completed || $todo?->working),
            'skills' => $todo ? $this->skillCatalogue($todo) : Skills::all(),
            'skillsAgent' => $todo ? Agent::label(Agent::effective($todo)) : Agent::name(),
            'otherLists' => $todo ? Checklist::mine()->whereKeyNot($todo->checklist_id)->orderBy('name')->get(['id', 'name']) : collect(),
        ];
    }
}
