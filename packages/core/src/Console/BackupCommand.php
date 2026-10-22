<?php

declare(strict_types=1);

namespace LynxCms\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
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
    #[Override]
    protected $signature = 'lynx:backup';

    #[Override]
    protected $description = 'Back up the schema and all pages as a content package (.zip)';

    public function handle(ContentExporter $exporter): int
    {
        $directory = Value::string(config('lynx.backups.path')) ?: storage_path('app/lynx/backups');
        File::ensureDirectoryExists($directory);
        $work = sys_get_temp_dir().'/lynx-backup-'.bin2hex(random_bytes(6));
        $zip = $directory.'/lynx-'.now()->format('Ymd-His').'.zip';

        $count = $exporter->export($work);
        PackageArchive::zip($work, $zip);
        File::deleteDirectory($work);
        $this->prune($directory);

        $this->components->info("Backed up {$count} page(s) to {$zip}.");

        return self::SUCCESS;
    }

    private function prune(string $directory): void
    {
        $backups = Value::strings(File::glob($directory.'/lynx-*.zip') ?: []);
        rsort($backups);

        foreach (array_slice($backups, max(1, Value::int(config('lynx.backups.keep'), 14))) as $old) {
            File::delete($old);
        }
    }
}
