<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Models\Attachment;
use Alle80\Griglia\Models\Checklist;
use Alle80\Griglia\Models\ContextBlock;
use Alle80\Griglia\Models\ContextGroup;
use Alle80\Griglia\Models\Ingredient;
use Alle80\Griglia\Models\Question;
use Alle80\Griglia\Models\Todo;
use Alle80\Griglia\Support\Tables;
use Alle80\Griglia\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class TablePrefixTest extends TestCase
{
    public function test_owned_tables_carry_the_prefix_and_the_others_do_not(): void
    {
        $this->assertSame('griglia_', Tables::prefix());
        $this->assertSame('griglia_todos', Tables::name('todos'));
        $this->assertSame('settings', Tables::name('settings'));
        $this->assertSame('users', Tables::name('users'));

        foreach (Tables::OWNED as $table) {
            $this->assertTrue(Schema::hasTable('griglia_'.$table), "table griglia_{$table}");
            $this->assertFalse(Schema::hasTable($table), "unprefixed table {$table} must not exist");
        }
    }

    public function test_every_model_resolves_the_prefixed_table(): void
    {
        $models = [
            Checklist::class => 'griglia_checklists',
            Todo::class => 'griglia_todos',
            Ingredient::class => 'griglia_ingredients',
            Attachment::class => 'griglia_attachments',
            Question::class => 'griglia_questions',
            ContextGroup::class => 'griglia_context_groups',
            ContextBlock::class => 'griglia_context_blocks',
        ];

        foreach ($models as $class => $table) {
            $this->assertSame($table, (new $class)->getTable(), $class);
        }
    }

    public function test_a_custom_prefix_is_honoured(): void
    {
        config()->set('griglia.table_prefix', 'board_');

        $this->assertSame('board_todos', Tables::name('todos'));
        $this->assertSame('board_todos', (new Todo)->getTable());

        config()->set('griglia.table_prefix', '');

        $this->assertSame('todos', Tables::name('todos'));
        $this->assertSame('todos', (new Todo)->getTable());
    }

    public function test_the_migration_renames_legacy_unprefixed_tables(): void
    {
        // A database created before the prefix: the legacy table sits next to nothing else
        Schema::rename('griglia_questions', 'questions');
        $this->assertFalse(Schema::hasTable('griglia_questions'));

        $migration = require __DIR__.'/../../database/migrations/2026_08_24_000000_prefix_griglia_tables.php';
        $migration->up();

        $this->assertTrue(Schema::hasTable('griglia_questions'));
        $this->assertFalse(Schema::hasTable('questions'));

        $migration->up(); // re-running finds nothing to rename
        $this->assertTrue(Schema::hasTable('griglia_questions'));
    }
}
