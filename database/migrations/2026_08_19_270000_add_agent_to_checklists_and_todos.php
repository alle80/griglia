<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Multi-agent: default agent of a list (`checklists.agent`) and per-task override (`todos.agent`). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable(Tables::name('checklists')) && ! Schema::hasColumn(Tables::name('checklists'), 'agent')) {
            Schema::table(Tables::name('checklists'), fn (Blueprint $table) => $table->string('agent', 40)->nullable()->after('plan_paused'));
        }
        if (Schema::hasTable(Tables::name('todos')) && ! Schema::hasColumn(Tables::name('todos'), 'agent')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->string('agent', 40)->nullable()->after('skills'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(Tables::name('todos'), 'agent')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->dropColumn('agent'));
        }
        if (Schema::hasColumn(Tables::name('checklists'), 'agent')) {
            Schema::table(Tables::name('checklists'), fn (Blueprint $table) => $table->dropColumn('agent'));
        }
    }
};
