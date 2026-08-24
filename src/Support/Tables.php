<?php

namespace Alle80\Griglia\Support;

/**
 * Names of the database tables owned by the package. They all carry the prefix set by
 * config `griglia.table_prefix` (GRIGLIA_TABLE_PREFIX, default `griglia_`) so that the
 * package does not squat on generic names such as `todos` or `attachments` in the host
 * database. Set the prefix to '' to keep the historical unprefixed names.
 *
 * Tables the package creates but does not own — `settings` (spatie/laravel-settings),
 * `notifications` (Laravel) and `push_subscriptions` (webpush) — are never prefixed:
 * their libraries look them up by their own configuration.
 */
class Tables
{
    /** The owned tables, without the prefix. */
    public const OWNED = [
        'checklists',
        'todos',
        'ingredients',
        'attachments',
        'questions',
        'context_groups',
        'context_blocks',
    ];

    public static function prefix(): string
    {
        $prefix = config('griglia.table_prefix', 'griglia_');

        return is_string($prefix) ? $prefix : '';
    }

    /** Real name of an owned table (any other name is returned untouched). */
    public static function name(string $table): string
    {
        return in_array($table, self::OWNED, true) ? self::prefix().$table : $table;
    }

    /** Owned tables as `bare name => real name`. */
    public static function map(): array
    {
        $map = [];

        foreach (self::OWNED as $table) {
            $map[$table] = self::name($table);
        }

        return $map;
    }
}
