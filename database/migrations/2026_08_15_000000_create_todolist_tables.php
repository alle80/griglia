<?php

use Alle80\Griglia\Support\Tables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated schema of the griglia package. Every step is guarded, so it is safe to run
 * on databases that were created by the older incremental migrations of the original app.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable(Tables::name('checklists'))) {
            Schema::create(Tables::name('checklists'), function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('plan_prompt')->nullable();                // plan mode: the prompt the list was built from
                $table->boolean('plan_paused')->default(false);      // plan mode: paused = the chain does not open the next task
                $table->string('agent', 40)->nullable();               // default agent of the list (multi-agent)
                $table->timestamps();
            });
        }

        if (! Schema::hasTable(Tables::name('todos'))) {
            Schema::create(Tables::name('todos'), function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->unsignedInteger('order')->index();
                $table->boolean('completed')->default(false);
                $table->timestamp('completed_at')->nullable()->index(); // when it was completed (history/statistics)
                $table->boolean('open_to_work')->default(false);   // user: ready for the agent
                $table->boolean('working')->default(false);        // agent: in progress
                $table->timestamp('stopped_at')->nullable();       // user stopped the agent
                $table->boolean('question')->default(false);       // agent has open questions
                $table->text('notes')->nullable();
                $table->text('claude_comment')->nullable();        // agent's answer (read-only in UI)
                $table->string('result_summary', 120)->nullable(); // short result shown below the task title
                $table->boolean('result_seen')->default(true);     // false = agent completed it, user hasn't opened the result yet
                $table->string('outcome', 16)->nullable();         // agent's result: ok | alert | blocked (colour of the highlight)
                $table->unsignedTinyInteger('progress')->nullable(); // 0-100 while working; set by griglia:check --progress
                $table->string('phase', 80)->nullable();             // what the agent is doing right now (griglia:check --phase)
                $table->timestamp('working_since')->nullable();     // start of the current working interval (stats)
                $table->unsignedInteger('work_seconds')->default(0); // total agent working time, closed intervals only (stats)
                $table->unsignedBigInteger('tokens_in')->default(0); // tokens reported by the agent (input, incl. cache)
                $table->unsignedBigInteger('tokens_out')->default(0); // tokens reported by the agent (output)
                $table->json('skills')->nullable();                 // agent skills chosen for this task (list of names)
                $table->string('agent', 40)->nullable();             // agent override for this task (multi-agent)
                $table->timestamp('archived_at')->nullable()->index();
                $table->timestamps();
                $table->foreignId('checklist_id')->nullable()->constrained(Tables::name('checklists'))->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained(Tables::name('todos'))->nullOnDelete();
                $table->foreignId('depends_on_id')->nullable()->constrained(Tables::name('todos'))->nullOnDelete(); // chained tasks (plan): opens when this one is done
            });
        }

        if (! Schema::hasTable(Tables::name('ingredients'))) {
            // Sub-tasks: historically named "ingredients" (the app started as a barbecue list). Do not rename.
            Schema::create(Tables::name('ingredients'), function (Blueprint $table) {
                $table->id();
                $table->foreignId('todo_id')->constrained(Tables::name('todos'))->cascadeOnDelete();
                $table->string('name');
                $table->unsignedInteger('order')->default(0);
                $table->boolean('checked')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable(Tables::name('attachments'))) {
            Schema::create(Tables::name('attachments'), function (Blueprint $table) {
                $table->id();
                $table->foreignId('todo_id')->constrained(Tables::name('todos'))->cascadeOnDelete();
                $table->string('path');
                $table->string('original_name');
                $table->text('description')->nullable();   // AI description, used by the search
                $table->string('mime', 100);
                $table->unsignedInteger('size');
                $table->unsignedSmallInteger('width')->nullable();
                $table->unsignedSmallInteger('height')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable(Tables::name('questions'))) {
            Schema::create(Tables::name('questions'), function (Blueprint $table) {
                $table->id();
                $table->foreignId('todo_id')->constrained(Tables::name('todos'))->cascadeOnDelete();
                $table->text('question');
                $table->text('answer')->nullable();
                $table->unsignedInteger('order')->default(0);
                $table->timestamps();
            });
        }

        // spatie/laravel-settings storage (only if the host app has not created it already)
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('group');
                $table->string('name');
                $table->boolean('locked')->default(false);
                $table->json('payload');
                $table->timestamps();
                $table->unique(['group', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::name('questions'));
        Schema::dropIfExists(Tables::name('attachments'));
        Schema::dropIfExists(Tables::name('ingredients'));
        Schema::dropIfExists(Tables::name('todos'));
        Schema::dropIfExists(Tables::name('checklists'));
    }
};
