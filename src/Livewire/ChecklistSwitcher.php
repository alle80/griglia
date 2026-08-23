<?php

namespace Alle80\Griglia\Livewire;

use Alle80\Griglia\Models\Checklist;
use Livewire\Component;

class ChecklistSwitcher extends Component
{
    public string $newName = '';

    /** List being renamed and its draft. */
    public ?int $editingId = null;

    public string $nameDraft = '';

    /** Archive view: the menu lists the archived lists instead of the active ones. */
    public bool $showArchived = false;

    public function startRename(int $checklistId): void
    {
        $list = Checklist::mine()->findOrFail($checklistId);
        $this->editingId = $list->id;
        $this->nameDraft = $list->name;
    }

    public function cancelRename(): void
    {
        $this->editingId = null;
        $this->nameDraft = '';
    }

    public function saveRename(): void
    {
        $name = trim($this->nameDraft);

        if ($name === '' || ! $this->editingId) {
            return;
        }

        Checklist::mine()->whereKey($this->editingId)->update(['name' => $name]);

        $wasCurrent = $this->editingId === Checklist::currentId();
        $this->cancelRename();
        $this->dispatch('toast', message: __('griglia::t.msg.list_renamed'));

        // The name of the current list is the page title: reload to refresh it everywhere
        if ($wasCurrent) {
            $this->js('window.location.reload()');
        }
    }

    public function switchTo(int $checklistId): void
    {
        if (! Checklist::mine()->whereKey($checklistId)->exists()) {
            return;
        }

        session(['checklist_id' => $checklistId]);
        $this->js('window.location.reload()');
    }

    /** Plan mode: the new list is built from a prompt (chained tasks). */
    public function create(): void
    {
        $name = trim($this->newName);

        if ($name === '') {
            return;
        }
        // A plan is written on its own page (/plans/new): here we only create plain lists.
        $list = Checklist::create(['name' => $name, 'user_id' => auth()->id()]);
        session(['checklist_id' => $list->id]);
        $this->js('window.location.reload()');
    }

    public function toggleArchived(): void
    {
        $this->showArchived = ! $this->showArchived;
        $this->cancelRename();
    }

    /** Archive a list: it disappears from the menu, its tasks stay. */
    public function archiveList(int $checklistId): void
    {
        // As for the deletion: the last active list cannot be archived
        if (Checklist::mine()->count() <= 1) {
            $this->dispatch('toast', message: __('griglia::t.msg.list_archive_last'), type: 'error');

            return;
        }

        $list = Checklist::mine()->whereKey($checklistId)->first();
        if (! $list) {
            return;
        }

        $list->update(['archived_at' => now()]);
        $this->dispatch('toast', message: __('griglia::t.msg.list_archived', ['name' => $list->name]), type: 'info');

        if ((int) session('checklist_id') === $checklistId) {
            session()->forget('checklist_id');
            $this->js('window.location.reload()');
        }
    }

    /** Bring an archived list back among the active ones. */
    public function restoreList(int $checklistId): void
    {
        $list = Checklist::mineArchived()->whereKey($checklistId)->first();
        if (! $list) {
            return;
        }

        $list->update(['archived_at' => null]);
        $this->dispatch('toast', message: __('griglia::t.msg.list_restored', ['name' => $list->name]));
    }

    public function deleteList(int $checklistId): void
    {
        // The last active list is untouchable (archived ones can always be deleted)
        $archived = Checklist::mineArchived()->whereKey($checklistId)->exists();
        if (! $archived && Checklist::mine()->count() <= 1) {
            return;
        }

        $list = Checklist::mineWithArchived()->whereKey($checklistId)->first();
        $list?->delete();
        $this->dispatch('toast', message: __('griglia::t.msg.list_deleted', ['name' => $list?->name]), type: 'info');

        if ((int) session('checklist_id') === $checklistId) {
            session()->forget('checklist_id');
            $this->js('window.location.reload()');
        }
    }

    /** Start a plan list from the menu: first not-started task → open to work, then switch to that list. */
    public function startPlan(int $checklistId): void
    {
        $list = Checklist::mine()->whereKey($checklistId)->first();
        if (! $list) {
            return;
        }
        $next = $list->todos()->whereNull('archived_at')->orderBy('order')->get()
            ->first(fn ($t) => ! $t->completed && ! $t->open_to_work && ! $t->working && ! $t->paused && ! $t->question);
        if ($next) {
            $next->update(['open_to_work' => true, 'stopped_at' => null]);
        }
        session(['checklist_id' => $list->id]);
        $this->js('window.location.reload()');
    }

    public function render()
    {
        $lists = ($this->showArchived ? Checklist::mineArchived() : Checklist::mine())->withCount([
            'todos' => fn ($q) => $q->whereNull('archived_at'),
            'todos as done_count' => fn ($q) => $q->whereNull('archived_at')->where('completed', true),
            'todos as chained_count' => fn ($q) => $q->whereNull('archived_at')->whereNotNull('depends_on_id'),
            'todos as running_count' => fn ($q) => $q->whereNull('archived_at')->where('completed', false)->where(fn ($w) => $w->where('open_to_work', true)->orWhere('working', true)->orWhere('paused', true)->orWhere('question', true)),
        ])->orderBy('id')->get();

        return view('griglia::livewire.checklist-switcher', [
            'lists' => $lists,
            'currentId' => Checklist::currentId(),
            'archivedCount' => Checklist::mineArchived()->count(),
        ]);
    }
}
