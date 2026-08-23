<?php

namespace Alle80\Griglia\Models;

use Alle80\Griglia\Database\Factories\ChecklistFactory;
use Alle80\Griglia\Mode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checklist extends Model
{
    /** @use HasFactory<ChecklistFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ChecklistFactory
    {
        return ChecklistFactory::new();
    }

    protected $fillable = ['name', 'user_id', 'plan_prompt', 'plan_paused', 'agent', 'archived_at'];

    protected static function booted(): void
    {
        // Deleting a list carries its tasks along: soft → soft (stats keep reading them, task 298),
        // force → force (so the attachment files are cleaned up too; the FK alone would leave them).
        static::deleting(function (Checklist $list) {
            $list->todos()->withTrashed()->get()
                ->each(fn (Todo $t) => $list->isForceDeleting() ? $t->forceDelete() : $t->delete());
        });
    }

    protected function casts(): array
    {
        return ['plan_paused' => 'boolean', 'archived_at' => 'datetime'];
    }

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('griglia.user_model', 'App\\Models\\User'));
    }

    /** Only the authenticated user's lists, archive excluded. */
    public static function mine(): Builder
    {
        return static::mineWithArchived()->whereNull('archived_at');
    }

    /** The user's lists, archive included (for the archive view and the restores). */
    public static function mineWithArchived(): Builder
    {
        // Local mode: one global set of lists (no users); server mode: the logged-in user's lists
        return Mode::isLocal() ? static::query() : static::where('user_id', auth()->id());
    }

    /** Only the user's archive. */
    public static function mineArchived(): Builder
    {
        return static::mineWithArchived()->whereNotNull('archived_at');
    }

    public function archived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Id of the user's current list (from the session, falling back to their first list). */
    public static function currentId(): int
    {
        $id = session('checklist_id');

        if ($id && static::mine()->whereKey($id)->exists()) {
            return (int) $id;
        }

        $first = static::mine()->orderBy('id')->first()
            ?? static::create(['name' => static::defaultName(), 'user_id' => auth()->id()]);

        session(['checklist_id' => $first->id]);

        return $first->id;
    }

    /** Name of a user's first list: the `griglia.default_list_name` config, or the translation when empty. */
    public static function defaultName(): string
    {
        $configured = trim((string) config('griglia.default_list_name', ''));

        return $configured !== '' ? $configured : __('griglia::t.default_list');
    }
}
