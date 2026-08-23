<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Interface language of the board ('' = same as the application config, APP_LOCALE). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('app.locale')) {
            $this->migrator->add('app.locale', '');
        }
    }

    public function down(): void
    {
        if ($this->migrator->exists('app.locale')) {
            $this->migrator->delete('app.locale');
        }
    }
};
