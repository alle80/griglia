<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Model and reasoning effort of the agent session (task 641): the board picks them, the persistent worker
 * passes them to the CLI. On the list they are the default of every task; on a task they override it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Tables::name('checklists'), function (Blueprint $table) {
            if (! Schema::hasColumn(Tables::name('checklists'), 'model')) {
                $table->string('model', 60)->nullable()->after('agent');
                $table->string('effort', 16)->nullable()->after('model');
            }
        });

        Schema::table(Tables::name('todos'), function (Blueprint $table) {
            if (! Schema::hasColumn(Tables::name('todos'), 'model')) {
                $table->string('model', 60)->nullable()->after('agent');
                $table->string('effort', 16)->nullable()->after('model');
            }
        });
    }

    public function down(): void
    {
        foreach (['checklists', 'todos'] as $table) {
            Schema::table(Tables::name($table), function (Blueprint $blueprint) {
                $blueprint->dropColumn(['model', 'effort']);
            });
        }
    }
};
