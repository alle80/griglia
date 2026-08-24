<?php

namespace Alle80\Griglia\Models;

use Alle80\Griglia\Database\Factories\QuestionFactory;
use Alle80\Griglia\Models\Concerns\HasPrefixedTable;
use Alle80\Griglia\Support\Live;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, HasPrefixedTable;

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }

    protected $fillable = ['todo_id', 'question', 'choices', 'answer', 'order'];

    protected function casts(): array
    {
        return ['choices' => 'array'];
    }

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    protected static function booted(): void
    {
        // Live update of the list/modal open elsewhere (Reverb)
        static::saved(fn ($m) => $m->todo && Live::todoChanged($m->todo));
        static::deleted(fn ($m) => $m->todo && Live::todoChanged($m->todo));
    }
}
