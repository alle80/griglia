<?php

namespace Alle80\Griglia\Livewire;

use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Settings\AppSettings;
use Alle80\Griglia\Themes;
use Livewire\Attributes\Layout;

#[Layout('griglia::layouts.themed')]
class ThemedTodoList extends TodoList
{
    public string $theme;

    public function mount(): void
    {
        $configured = app(AppSettings::class)->default_style;
        $this->theme = Themes::has($configured) ? $configured : Themes::default();
    }

    public function render()
    {
        $t = Themes::get($this->theme);
        $list = Checklist::find(Checklist::currentId());

        return view('griglia::livewire.todo-list', [
            'todos' => $this->todos(),
            't' => $t,
            'listName' => $this->listName(),
            'archivedCount' => $this->archivedCount(),
            'filtering' => $this->isFiltering(),
            'plan' => $this->planStatus(),
            'listAgent' => (string) ($list?->agent ?? ''),
            'listModel' => (string) ($list?->model ?? ''),
            'listEffort' => (string) ($list?->effort ?? ''),
        ])->title($this->listName().' — '.$t['label']);
    }
}
