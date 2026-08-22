<?php

namespace Alle80\Griglia\Tests;

use Alle80\Griglia\GrigliaServiceProvider;
use Alle80\Griglia\Mode;
use Alle80\Griglia\Tests\Support\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\LivewireServiceProvider;
use NotificationChannels\WebPush\WebPushServiceProvider;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** @var bool compiled Blade cache emptied once per process (see below) */
    private static bool $viewsCleared = false;

    use WithLaravelMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // The compiled Blade cache lives in the shared testbench storage: a file compiled from another branch
        // (or by another agent working on its own checkout) is reused whenever the source is older than it,
        // and the suite fails on code that does not exist any more. Start every run from an empty cache.
        if (! self::$viewsCleared) {
            self::$viewsCleared = true;
            foreach (glob(rtrim((string) config('view.compiled'), '/').'/*.php') ?: [] as $compiled) {
                @unlink($compiled);
            }
        }
        Mode::reset(); // static cache must not leak between tests
        $this->withoutVite();

    }

    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, LaravelSettingsServiceProvider::class, WebPushServiceProvider::class, GrigliaServiceProvider::class];
    }

    protected function tearDownInteractsWithMigrations(): void
    {
        // RefreshDatabase owns schema cleanup; Testbench host-first rollback violates MySQL foreign keys.
    }

    protected function destroyDatabaseMigrations(): void
    {
        // RefreshDatabase drops all tables; rolling back host migrations first breaks MySQL foreign keys.
    }

    protected function defineEnvironment($app): void
    {
        $connection = getenv('GRIGLIA_TEST_DB') ?: 'testing';

        $app['config']->set('database.default', $connection);
        if ($connection === 'testing') {
            $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        } elseif ($connection === 'mysql') {
            $app['config']->set('database.connections.mysql', [
                'driver' => 'mysql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '3306',
                'database' => getenv('DB_DATABASE') ?: 'griglia_test',
                'username' => getenv('DB_USERNAME') ?: 'root',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);
        }
        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('griglia.user_model', User::class);
        $app['config']->set('griglia.agent_list', 'dev');
        $app['config']->set('webpush.database_connection', $connection);
        $app['config']->set('filesystems.disks.public', ['driver' => 'local', 'root' => storage_path('framework/testing/public'), 'url' => 'http://localhost/storage']);
    }

    /** A logged-in user with its default list. */
    protected function actingAsUser(string $email = 'user@example.com'): User
    {
        $user = User::create(['name' => 'User', 'email' => $email, 'password' => bcrypt('secret')]);
        $this->actingAs($user);

        return $user;
    }
}
