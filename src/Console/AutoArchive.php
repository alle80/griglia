<?php

namespace Alle80\Griglia\Console;

use Alle80\Griglia\Models\Todo;
use Alle80\Griglia\Settings\AppSettings;
use Illuminate\Console\Command;

/**
 * Archives by itself the todos completed more than N days ago (setting «Automatic archiving»
 * in /settings; 0 = never). Runs every night from the scheduler (routes/console.php).
 */
class AutoArchive extends Command
{
    protected $signature = 'griglia:auto-archive {--dry-run : Only show what would be archived}';

    protected $aliases = ['todos:auto-archive'];

    protected $description = 'Archives completed todos older than N days (see /settings)';

    public function handle(): int
    {
        $days = (int) app(AppSettings::class)->auto_archive_days;

        if ($days <= 0) {
            $this->line('Automatic archiving is off (0 days).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $query = Todo::whereNull('archived_at')->where('completed', true)->where('updated_at', '<', $cutoff);

        $count = 0;
        // Per list, from the bottom, so the numbering of the active ones stays compact
        foreach ($query->orderByDesc('order')->get()->groupBy('checklist_id') as $todos) {
            foreach ($todos as $todo) {
                $count++;
                if ($this->option('dry-run')) {
                    $this->line("- {$todo->title} (list {$todo->checklist_id}, completed on {$todo->updated_at->format('d/m')})");

                    continue;
                }
                $todo->update(['archived_at' => now()]);
                Todo::where('checklist_id', $todo->checklist_id)->whereNull('archived_at')->where('order', '>', $todo->order)->decrement('order');
            }
        }

        $this->info(sprintf('%s %d items completed more than %d days ago.', $this->option('dry-run') ? 'To archive:' : 'Archived', $count, $days));

        return self::SUCCESS;
    }
}
