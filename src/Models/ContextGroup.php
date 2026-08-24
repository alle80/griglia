<?php

namespace Alle80\Griglia\Models;

use Alle80\Griglia\Models\Concerns\HasPrefixedTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A group of context blocks (a `##` section of the agent's instructions file). */
class ContextGroup extends Model
{
    use HasPrefixedTable;

    protected $fillable = ['title', 'order', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'order' => 'integer'];
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ContextBlock::class, 'group_id')->orderBy('order')->orderBy('id');
    }
}
