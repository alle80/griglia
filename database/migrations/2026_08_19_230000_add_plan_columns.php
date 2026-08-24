<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Plan mode: the plan prompt on the list, and the chain between tasks (`depends_on_id`). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable(Tables::name('checklists')) && ! Schema::hasColumn(Tables::name('checklists'), 'plan_prompt')) {
            Schema::table(Tables::name('checklists'), fn (Blueprint $table) => $table->text('plan_prompt')->nullable()->after('name'));
        }
        if (Schema::hasTable(Tables::name('todos')) && ! Schema::hasColumn(Tables::name('todos'), 'depends_on_id')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->foreignId('depends_on_id')->nullable()->after('parent_id')->constrained(Tables::name('todos'))->nullOnDelete());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(Tables::name('todos'), 'depends_on_id')) {
            Schema::table(Tables::name('todos'), function (Blueprint $table) {
                $table->dropConstrainedForeignId('depends_on_id');
            });
        }
        if (Schema::hasColumn(Tables::name('checklists'), 'plan_prompt')) {
            Schema::table(Tables::name('checklists'), fn (Blueprint $table) => $table->dropColumn('plan_prompt'));
        }
    }
};
