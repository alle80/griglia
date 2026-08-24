<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds `checklists.archived_at`: an archived list leaves the switcher, keeping its tasks. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable(Tables::name('checklists')) && ! Schema::hasColumn(Tables::name('checklists'), 'archived_at')) {
            Schema::table(Tables::name('checklists'), function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->index()->after('plan_paused');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(Tables::name('checklists'), 'archived_at')) {
            Schema::table(Tables::name('checklists'), fn (Blueprint $table) => $table->dropColumn('archived_at'));
        }
    }
};
