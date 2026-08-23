<?php

namespace Alle80\Griglia\Events;

use Alle80\Griglia\Mode;
use Alle80\Griglia\Models\Todo;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something changed in a todo (state, title, sub-task…): it is announced through
 * Reverb to the owner of the list, so the open pages (desktop, phone)
 * refresh without reloading. See Alle80\Griglia\Support\Live.
 */
class TodoChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $userId,
        public int $checklistId,
        public int $todoId,
        public string $title,
        public string $state,
        /** 'cli' when the change comes from artisan (e.g. the agent's griglia:check), 'web' otherwise */
        public string $source,
        public bool $deleted = false,
        /** true when the state changed (tick / 🟢 / 🔧 / ❓): the page shows a toast */
        public bool $stateChanged = false,
    ) {}

    public static function stateOf(Todo $todo): string
    {
        return match (true) {
            (bool) $todo->completed => 'done',
            (bool) $todo->question => 'question',
            (bool) $todo->paused => 'paused',
            (bool) $todo->working => 'working',
            (bool) $todo->open_to_work => 'otw',
            default => 'waiting',
        };
    }

    public function broadcastOn(): PrivateChannel|Channel
    {
        if (Mode::isLocal()) {
            return new Channel(Mode::broadcastChannel());
        }

        return new PrivateChannel(Mode::broadcastChannel($this->userId));
    }

    public function broadcastAs(): string
    {
        return 'TodoChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'checklist_id' => $this->checklistId,
            'todo_id' => $this->todoId,
            'title' => $this->title,
            'state' => $this->state,
            'source' => $this->source,
            'deleted' => $this->deleted,
            'state_changed' => $this->stateChanged,
        ];
    }
}
