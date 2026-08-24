<?php

namespace Alle80\Griglia\Models\Concerns;

use Alle80\Griglia\Support\Tables;

/**
 * Resolves the model's table through Tables, so every model of the package follows the
 * `griglia.table_prefix` configuration instead of squatting a generic name in the host database.
 */
trait HasPrefixedTable
{
    public function getTable(): string
    {
        return Tables::name(parent::getTable());
    }
}
