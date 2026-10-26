<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LynxCms\Core\Console\Concerns\TerminalFeedback;
use LynxCms\Core\Content\BackupFiles;
use LynxCms\Core\Content\ContentExporter;
use LynxCms\Core\Content\PackageArchive;
use LynxCms\Core\Support\Value;
use Override;

/**
 * A content backup (ADR-0040): the schema and every page as a .zip in lynx.backups.path, keeping
 * the newest lynx.backups.keep. Restore with lynx:import <zip> --with-schema --prune. It does not
 * replace a database backup: activity, revisions, users and app tables are not in it.
 */
final class BackupCommand extends Command
{
    use TerminalFeedback;

    #[Override]
    protected $signature = 'lynx:backup';

    #[Override]
    protected $description = 'Back up the schema and all pages as a content package (.zip)';

    public function handle(ContentExporter $exporter): int
    {
        $directory = BackupFiles::directory();
        File::ensureDirectoryExists($directory);
        $work = sys_get_temp_dir().'/lynx-backup-'.bin2hex(random_bytes(6));
        $zip = $directory.'/lynx-'.now()->format('Ymd-His').'.zip';

        $count = $this->working('Backing up...', function () use ($exporter, $work, $zip): int {
            $count = $exporter->export($work);
            PackageArchive::zip($work, $zip);
            File::deleteDirectory($work);

            return $count;
        });

        $this->prune();

        $this->components->info("Backed up {$count} page(s) to {$zip}.");

        return self::SUCCESS;
    }

    private function prune(): void
    {
        foreach (array_slice(BackupFiles::latestFirst(), max(1, Value::int(config('lynx.backups.keep'), 14))) as $old) {
            File::delete($old);
        }
    }
}
