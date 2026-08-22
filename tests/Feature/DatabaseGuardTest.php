<?php

namespace Alle80\Griglia\Tests\Feature;

use Alle80\Griglia\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The suite drops every table of the connection it is given, so it must never be handed a
 * real database: on 2026-08-22 a run inside the application container inherited its DB_*
 * variables and wiped the live board.
 */
class DatabaseGuardTest extends TestCase
{
    private function defineConnection(string $database): void
    {
        config()->set('database.connections.guard-probe', [
            'driver' => 'mysql',
            'host' => 'db',
            'port' => '3306',
            'database' => $database,
            'username' => 'laravel',
            'password' => 'secret',
        ]);
        DB::purge('guard-probe');
    }

    public function test_it_refuses_a_connection_to_a_database_that_is_not_a_test_database(): void
    {
        $this->defineConnection('laravel');

        try {
            DB::connection('guard-probe');
            $this->fail('The guard let a production database through.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('does not look like a test database', $e->getMessage());
            $this->assertStringContainsString('laravel on db', $e->getMessage());
        }
    }

    public function test_it_allows_a_test_database_and_sqlite(): void
    {
        $this->defineConnection('griglia_test'); // the name CI uses

        $this->assertSame('griglia_test', DB::connection('guard-probe')->getDatabaseName());
        $this->assertNotNull(DB::connection()->getPdo()); // the sqlite connection of the suite itself
    }
}
