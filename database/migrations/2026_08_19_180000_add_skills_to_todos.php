<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds `todos.skills` (agent skills chosen for the task) for pre-existing installs. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable(Tables::name('todos')) && ! Schema::hasColumn(Tables::name('todos'), 'skills')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->json('skills')->nullable()->after('tokens_out'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(Tables::name('todos'), 'skills')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->dropColumn('skills'));
        }
    }
};
