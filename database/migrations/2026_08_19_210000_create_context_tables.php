<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Agent context (the instructions file, e.g. CLAUDE.md) split into groups and blocks, each switchable. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable(Tables::name('context_groups'))) {
            Schema::create(Tables::name('context_groups'), function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->unsignedInteger('order')->default(0);
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable(Tables::name('context_blocks'))) {
            Schema::create(Tables::name('context_blocks'), function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained(Tables::name('context_groups'))->cascadeOnDelete();
                $table->string('title')->nullable();
                $table->text('body');
                $table->unsignedInteger('order')->default(0);
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::name('context_blocks'));
        Schema::dropIfExists(Tables::name('context_groups'));
    }
};
