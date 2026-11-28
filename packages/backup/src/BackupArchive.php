<?php

declare(strict_types=1);

namespace LynxCms\Backup;

use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use LynxCms\Backup\Dumpers\SqliteCopy;
use LynxCms\Core\Support\Value;
use Spatie\Backup\BackupDestination\BackupDestinationFactory;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\Backup\Tasks\Cleanup\CleanupJob;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;
use Spatie\DbDumper\Databases\Sqlite;
use Spatie\DbDumper\DbDumper;

/**
 * Makes a backup's zip with spatie/laravel-backup, from lynx-backup's settings (ADR-0158): the
 * database of one connection, the files when asked, copied to each disk; then Spatie's cleanup.
 * Lynx supports SQLite and PostgreSQL, and backs up nothing else.
 *
 * @internal
 */
final readonly class BackupArchive
{
    /** The drivers Lynx supports, and so the ones it backs up. */
    public const array DRIVERS = ['sqlite', 'pgsql'];

    public const string SQLITE_COPY = 'sqlite-copy';

    public const string SQLITE_DUMP = 'sqlite-dump';

    public const string POSTGRES = 'pgsql';

    /** Spatie's time format, before the id; its cleanup reads the time from the file's date. */
    private const string FILENAME_FORMAT = 'Y-m-d-H-i-s';

    public function __construct(private Filesystems $filesystems) {}

    public function connection(): string
    {
        $connection = Value::string(config('lynx-backup.connection'));

        return $connection === '' ? Value::string(config('database.default')) : $connection;
    }

    public function isSupported(): bool
    {
        return in_array($this->driver(), self::DRIVERS, true);
    }

    /** How the database goes into the zip: an SQLite copy or dump, or pg_dump's SQL. */
    public function format(): string
    {
        if ($this->driver() !== 'sqlite') {
            return self::POSTGRES;
        }

        return Value::string(config('lynx-backup.sqlite_format')) === 'dump' ? self::SQLITE_DUMP : self::SQLITE_COPY;
    }

    /** @return list<string> */
    public function disks(): array
    {
        return array_values(array_filter(array_map(trim(...), Value::strings(config('lynx-backup.disks')))));
    }

    public function includesFiles(): bool
    {
        return Value::bool(config('lynx-backup.include_files'));
    }

    /**
     * Makes the zip and copies it to every disk. Its name holds the time and the backup's id, so
     * two backups in the same second never share a file.
     *
     * @return string the zip's path on each disk
     */
    public function make(int $backup): string
    {
        $format = $this->format();
        DbDumperFactory::extend('sqlite', fn (): DbDumper => $format === self::SQLITE_COPY ? new SqliteCopy : new Sqlite);
        $filename = Carbon::now()->format(self::FILENAME_FORMAT)."-{$backup}.zip";

        BackupJobFactory::createFromConfig($this->config())->setFilename($filename)->disableSignals()->run();

        return $this->path().'/'.$filename;
    }

    /** Spatie's cleanup on every disk: its default strategy keeps fewer backups as they age. */
    public function cleanUp(): void
    {
        new CleanupJob(BackupDestinationFactory::createFromArray($this->config()), app(CleanupStrategy::class))->run();
    }

    /** The zip's size on the first disk that has it, in bytes. */
    public function size(string $path): int
    {
        foreach ($this->disks() as $disk) {
            if ($this->filesystems->disk($disk)->exists($path)) {
                return $this->filesystems->disk($disk)->size($path);
            }
        }

        return 0;
    }

    /**
     * Whether any of the disks still has the zip.
     *
     * @param  list<string>  $disks
     */
    public function exists(string $path, array $disks): bool
    {
        return array_any($disks, fn (string $disk) => $this->filesystems->disk($disk)->exists($path));
    }

    /** @param  list<string>  $disks */
    public function delete(string $path, array $disks): void
    {
        foreach ($disks as $disk) {
            $this->filesystems->disk($disk)->delete($path);
        }
    }

    private function driver(): string
    {
        return Value::string(config("database.connections.{$this->connection()}.driver"));
    }

    private function path(): string
    {
        return Value::string(config('lynx-backup.path')) ?: 'backups';
    }

    private function config(): Config
    {
        $temporary = Value::string(config('backup.backup.temporary_directory')) ?: storage_path('app/backup-temp');

        return Config::fromArray([
            'backup' => [
                'name' => $this->path(),
                'source' => [
                    'files' => [
                        'include' => $this->includesFiles() ? Value::strings(config('lynx-backup.files')) : [],
                        'exclude' => [...$this->localBackupFolders(), $temporary],
                        'follow_links' => false,
                        'ignore_unreadable_directories' => true,
                        'relative_path' => null,
                    ],
                    'databases' => [$this->connection()],
                ],
                'database_dump_filename_base' => 'connection',
                'database_dump_file_extension' => $this->format() === self::SQLITE_COPY ? 'sqlite' : '',
                'destination' => ['disks' => $this->disks()],
                'temporary_directory' => $temporary,
                'password' => Value::string(config('lynx-backup.password')),
            ],
            'cleanup' => is_array(config('backup.cleanup')) ? config('backup.cleanup') : [],
        ]);
    }

    /**
     * The backup folders on local disks, which a backup with files must leave out.
     *
     * @return list<string>
     */
    private function localBackupFolders(): array
    {
        $folders = [];

        foreach ($this->disks() as $disk) {
            $filesystem = $this->filesystems->disk($disk);

            if ($filesystem instanceof FilesystemAdapter && Value::string(config("filesystems.disks.{$disk}.driver")) === 'local') {
                $folders[] = $filesystem->path($this->path());
            }
        }

        return $folders;
    }
}
