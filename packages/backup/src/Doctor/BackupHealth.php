<?php

declare(strict_types=1);

namespace LynxCms\Backup\Doctor;

use Illuminate\Support\Carbon;
use LynxCms\Backup\BackupArchive;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\DumpBinaries;
use LynxCms\Backup\FreeSpace;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Support\LocalizedNumber;
use LynxCms\Core\Support\Value;

/**
 * What puts the database backups at risk (ADR-0158): a database Lynx cannot back up, a missing
 * dump binary, a backup stuck on the queue, a failed latest backup, no finished backup in a day
 * while they are scheduled, one disk only, too little free space, the cache in the backed-up
 * database, and zips without a password. Each problem is one finding.
 *
 * @internal
 */
final readonly class BackupHealth implements Check
{
    /** A daily backup that runs a little late still counts. */
    private const int MAX_AGE_HOURS = 26;

    /** A backup queued or running this long has no worker, or its worker died. */
    private const int STUCK_MINUTES = 30;

    public function __construct(private BackupArchive $archive, private DumpBinaries $binaries, private FreeSpace $space) {}

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
            $this->stuck(),
            $this->failedLatest() ?? $this->tooOld(),
            $this->oneDisk(),
            $this->lowSpace(),
            $this->cacheInDatabase(),
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

    private function stuck(): ?Finding
    {
        $ids = StoredBackup::query()
            ->whereIn('status', [BackupStatus::Queued, BackupStatus::Running])
            ->where('updated_at', '<', Carbon::now()->subMinutes(self::STUCK_MINUTES))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return null;
        }

        return new Finding(Severity::Warning, count($ids).' backup(s) queued or running for more than '.self::STUCK_MINUTES.' minutes: #'.implode(', #', Value::ints($ids)).'.', 'Check that a queue worker runs (php artisan queue:work) and read failed_jobs. lynx:backup:database backs up without the queue.');
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

    /** Room for two more zips the size of the last one, on each local disk. */
    private function lowSpace(): ?Finding
    {
        $size = Value::int(StoredBackup::query()->where('status', BackupStatus::Done)->latest()->latest('id')->value('size'));

        if ($size === 0) {
            return null;
        }

        $low = array_values(array_filter($this->archive->disks(), function (string $disk) use ($size): bool {
            $free = $this->space->on($disk);

            return $free !== null && $free < 2 * $size;
        }));

        if ($low === []) {
            return null;
        }

        return new Finding(Severity::Warning, 'Less free space than two more backups ('.LocalizedNumber::fileSize(2 * $size).') on: '.implode(', ', $low).'.', 'Free space on the disk, move backups to a larger disk (LYNX_BACKUP_DISKS), or keep fewer (backup.cleanup in config/backup.php).');
    }

    /** A cache in the backed-up database goes into every backup, and is often most of it. */
    private function cacheInDatabase(): ?Finding
    {
        $store = Value::string(config('cache.default'));

        if (Value::string(config("cache.stores.{$store}.driver")) !== 'database') {
            return null;
        }

        $connection = Value::string(config("cache.stores.{$store}.connection")) ?: Value::string(config('database.default'));

        if ($connection !== $this->archive->connection()) {
            return null;
        }

        return new Finding(Severity::Warning, 'The cache is stored in the database that is backed up, so every backup carries it.', 'Set CACHE_STORE=file, or redis where the server has it.');
    }

    private function noPassword(): ?Finding
    {
        if (Value::string(config('lynx-backup.password')) !== '') {
            return null;
        }

        return new Finding(Severity::Warning, 'Backups are not encrypted. They hold every password hash and two-factor secret.', 'Set LYNX_BACKUP_PASSWORD, and keep it somewhere other than the server.');
    }
}
