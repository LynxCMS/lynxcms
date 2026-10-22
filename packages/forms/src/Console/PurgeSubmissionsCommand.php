<?php

declare(strict_types=1);

namespace LynxCms\Forms\Console;

use Illuminate\Console\Command;
use LynxCms\Forms\Actions\PurgeSubmissions;
use Override;

/**
 * Deletes old spam and archived submissions with their files (forms.purge); scheduled daily.
 */
final class PurgeSubmissionsCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:forms:purge {--days= : Older than this many days (default: lynx-forms.purge_after_days)}';

    #[Override]
    protected $description = 'Delete old spam and archived form submissions with their files';

    public function handle(): int
    {
        $days = $this->option('days');
        $count = lynx()->run(PurgeSubmissions::class, is_numeric($days) ? ['older_than_days' => (int) $days] : [])->value;
        $this->components->info("Deleted {$count} submissions.");

        return self::SUCCESS;
    }
}
