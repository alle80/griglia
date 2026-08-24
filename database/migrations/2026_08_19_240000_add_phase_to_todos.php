<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds `todos.phase` (short text of what the agent is doing, shown next to the progress %). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable(Tables::name('todos')) && ! Schema::hasColumn(Tables::name('todos'), 'phase')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->string('phase', 80)->nullable()->after('progress'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(Tables::name('todos'), 'phase')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->dropColumn('phase'));
        }
    }
};
