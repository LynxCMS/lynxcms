<?php

declare(strict_types=1);

namespace LynxCms\Backup\Doctor;

use Illuminate\Support\Carbon;
use LynxCms\Backup\BackupArchive;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\DumpBinaries;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Support\Value;

/**
 * What puts the database backups at risk (ADR-0158): a database Lynx cannot back up, a missing
 * dump binary, a failed latest backup, no finished backup in a day while they are scheduled, one
 * disk only, and zips without a password. Each problem is one finding.
 *
 * @internal
 */
final readonly class BackupHealth implements Check
{
    /** A daily backup that runs a little late still counts. */
    private const int MAX_AGE_HOURS = 26;

    public function __construct(private BackupArchive $archive, private DumpBinaries $binaries) {}

    public function key(): string
    {
        return 'backups';
    }

    public function label(): string
    {
        return 'Database backups are made and kept safe';
    }

    public function run(): array
    {
        if (! $this->archive->isSupported()) {
            return [new Finding(Severity::Error, "The [{$this->archive->connection()}] connection uses [{$this->archive->driver()}]. Lynx backs up SQLite and PostgreSQL only.", 'Set lynx-backup.connection (LYNX_BACKUP_CONNECTION) to the SQLite or PostgreSQL connection Lynx uses.')];
        }

        return array_values(array_filter([
            $this->missingBinary(),
            $this->failedLatest() ?? $this->tooOld(),
            $this->oneDisk(),
            $this->noPassword(),
        ]));
    }

    private function missingBinary(): ?Finding
    {
        $binary = match ($this->archive->format()) {
            BackupArchive::POSTGRES => 'pg_dump',
            BackupArchive::SQLITE_DUMP => 'sqlite3',
            default => null,
        };

        if ($binary === null || $this->binaries->has($binary)) {
            return null;
        }

        return new Finding(Severity::Error, "Backups need {$binary}, which this server does not have.", $binary === 'sqlite3'
            ? 'Install sqlite3, or back up SQLite as a copy of the database file in Settings > Backups.'
            : 'Install the PostgreSQL client tools, or set the folder that has pg_dump as dump.dump_binary_path on the connection.');
    }

    private function failedLatest(): ?Finding
    {
        $latest = StoredBackup::query()->whereIn('status', [BackupStatus::Done, BackupStatus::Failed])->latest()->latest('id')->first();

        if ($latest?->status !== BackupStatus::Failed) {
            return null;
        }

        return new Finding(Severity::Error, "The latest backup (#{$latest->id}) failed: {$latest->error}", 'Fix the cause, then run php artisan lynx:backup:database.');
    }

    private function tooOld(): ?Finding
    {
        if (Value::string(config('lynx-backup.schedule')) === '') {
            return null;
        }

        $since = Carbon::now()->subHours(self::MAX_AGE_HOURS);

        if (StoredBackup::query()->where('status', BackupStatus::Done)->where('created_at', '>=', $since)->exists()) {
            return null;
        }

        return new Finding(Severity::Warning, 'No database backup finished in the last '.self::MAX_AGE_HOURS.' hours.', 'Check that the scheduler runs (php artisan schedule:work, or a cron entry for schedule:run), or run php artisan lynx:backup:database.');
    }

    private function oneDisk(): ?Finding
    {
        if (count($this->archive->disks()) > 1) {
            return null;
        }

        return new Finding(Severity::Warning, 'Backups go to one disk only: a server that fails takes them with it.', 'Add an off-site disk: LYNX_BACKUP_DISKS=local,s3.');
    }

    private function noPassword(): ?Finding
    {
        if (Value::string(config('lynx-backup.password')) !== '') {
            return null;
        }

        return new Finding(Severity::Warning, 'Backups are not encrypted. They hold every password hash and two-factor secret.', 'Set LYNX_BACKUP_PASSWORD, and keep it somewhere other than the server.');
    }
}
