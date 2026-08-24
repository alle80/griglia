<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the tables owned by the package behind the `griglia.table_prefix` prefix (task 640), so that
 * an installation no longer squats generic names such as `todos` or `attachments` in the host
 * database. Existing tables are renamed, data and foreign keys included; on a fresh install the
 * tables are already created prefixed and this migration finds nothing to do.
 *
 * Tables the package creates but does not own — `settings`, `notifications`, `push_subscriptions` —
 * belong to third-party libraries and are deliberately left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Tables::map() as $from => $to) {
            $this->move($from, $to);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(Tables::map()) as $to => $from) {
            $this->move($from, $to);
        }
    }

    /** Renames only when there is exactly one table to rename, so re-running is harmless. */
    private function move(string $from, string $to): void
    {
        if ($from !== $to && Schema::hasTable($from) && ! Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }
};
