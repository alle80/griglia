<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds `todos.completed_at` (history/statistics) and backfills it from `updated_at` for already completed items. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable(Tables::name('todos'))) {
            return;
        }
        if (! Schema::hasColumn(Tables::name('todos'), 'completed_at')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->timestamp('completed_at')->nullable()->index()->after('completed'));
        }
        DB::table(Tables::name('todos'))->where('completed', true)->whereNull('completed_at')->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn(Tables::name('todos'), 'completed_at')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->dropColumn('completed_at'));
        }
    }
};
