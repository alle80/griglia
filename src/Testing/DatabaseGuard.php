<?php

namespace Alle80\Griglia\Testing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use RuntimeException;

/**
 * Stops a test run from destroying a real database.
 *
 * The package suite uses RefreshDatabase and Testbench's workbench boots with
 * `migrate:fresh`: both DROP every table of whatever connection they are given.
 * When the suite (or `vendor/bin/testbench`) runs inside an application
 * container, the process inherits that application's DB_* variables, so the
 * "test" connection silently resolves to the live database — which is how the
 * dev board lost its data on 2026-08-22.
 *
 * The rule is deliberately blunt: inside a test process, only SQLite or a
 * database whose name says "test" may be touched. Everything else aborts before
 * a single migration runs. `GRIGLIA_ALLOW_PROD_DB=1` opts out for the rare case
 * where the name cannot follow the convention.
 */
final class DatabaseGuard
{
    /** Env var that disables the guard, mirroring GRIGLIA_ALLOW_STALE on the pre-push hook. */
    public const ESCAPE_HATCH = 'GRIGLIA_ALLOW_PROD_DB';

    /**
     * Arms the guard: from now on any database connection opened by this process is checked
     * before it can run a statement. The check cannot happen while providers register — Testbench
     * applies the test connection *after* that, so the configuration read there is still the one
     * inherited from the environment.
     */
    public static function protect(Application $app): void
    {
        if (! self::isTestProcess($app) || self::disabled()) {
            return;
        }

        $app['events']->listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            self::assertSafe($event->connection);
        });
    }

    /** @throws RuntimeException when a test process opened a connection to a non-test database */
    public static function assertSafe(Connection $connection): void
    {
        $config = $connection->getConfig();
        $driver = (string) ($config['driver'] ?? '');
        $database = (string) ($config['database'] ?? '');

        if ($driver === 'sqlite' || self::looksLikeTestDatabase($database)) {
            return;
        }

        $host = (string) ($config['host'] ?? '');

        throw new RuntimeException(implode(PHP_EOL, [
            'Refusing to run tests against a database that does not look like a test database.',
            '  connection: '.$connection->getName()." ({$driver})",
            "  database:   {$database}".($host !== '' ? " on {$host}" : ''),
            '',
            'The suite drops every table of this connection. Point it at SQLite (the default) or at a',
            'database whose name contains "test", e.g. DB_DATABASE=griglia_test. Never run the suite,',
            '`vendor/bin/phpunit` or `vendor/bin/testbench` inside the application container: it exports',
            'the DB_* credentials of the live database.',
            '',
            'If the name really cannot follow the convention, set '.self::ESCAPE_HATCH.'=1.',
        ]));
    }

    /** phpunit, or the Testbench/workbench skeleton driving `vendor/bin/testbench`. */
    private static function isTestProcess(Application $app): bool
    {
        if ($app->runningUnitTests()) {
            return true;
        }

        $skeleton = DIRECTORY_SEPARATOR.'testbench-core'.DIRECTORY_SEPARATOR.'laravel';

        return str_contains($app->basePath(), $skeleton)
            || (bool) getenv('TESTBENCH_WORKING_PATH')
            || defined('TESTBENCH_WORKING_PATH');
    }

    private static function looksLikeTestDatabase(string $database): bool
    {
        return $database === ':memory:' || preg_match('/test/i', $database) === 1;
    }

    private static function disabled(): bool
    {
        return filter_var(getenv(self::ESCAPE_HATCH) ?: '', FILTER_VALIDATE_BOOLEAN);
    }
}
