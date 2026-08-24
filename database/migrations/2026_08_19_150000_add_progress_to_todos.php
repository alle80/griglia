<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds `todos.progress` (0–100 while the agent is working) for pre-existing installs. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable(Tables::name('todos')) && ! Schema::hasColumn(Tables::name('todos'), 'progress')) {
            Schema::table(Tables::name('todos'), function (Blueprint $table) {
                $table->unsignedTinyInteger('progress')->nullable()->after('result_seen');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(Tables::name('todos'), 'progress')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->dropColumn('progress'));
        }
    }
};
