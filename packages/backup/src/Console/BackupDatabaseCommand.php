<?php

declare(strict_types=1);

namespace LynxCms\Backup\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Number;
use LynxCms\Contracts\Backups\CouldNotBackUp;
use LynxCms\Contracts\Backups\DatabaseBackups;
use LynxCms\Core\Support\Value;
use Override;

/**
 * Makes a database backup now, in this process, as the scheduler does every day.
 *
 * @internal
 */
final class BackupDatabaseCommand extends Command
{
    /** @var string */
    #[Override]
    protected $signature = 'lynx:backup:database {--reason=Backup from the command line : Why the backup is made}';

    /** @var string */
    #[Override]
    protected $description = 'Back up the database to the backup disks';

    public function handle(DatabaseBackups $backups): int
    {
        try {
            $backup = $backups->create(Value::string($this->option('reason')));
        } catch (CouldNotBackUp $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Backed up to {$backup->path} (".Number::fileSize($backup->size).') on '.implode(', ', $backup->disks).'.');

        return self::SUCCESS;
    }
}
