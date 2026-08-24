<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable(Tables::name('todos')) && ! Schema::hasColumn(Tables::name('todos'), 'result_summary')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->string('result_summary', 120)->nullable()->after('claude_comment'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(Tables::name('todos'), 'result_summary')) {
            Schema::table(Tables::name('todos'), fn (Blueprint $table) => $table->dropColumn('result_summary'));
        }
    }
};
