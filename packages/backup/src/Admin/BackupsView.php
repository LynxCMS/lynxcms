<?php

declare(strict_types=1);

namespace LynxCms\Backup\Admin;

use Illuminate\Support\Carbon;
use LynxCms\Admin\Editor\ActorNames;
use LynxCms\Backup\BackupArchive;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\DumpBinaries;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Core\Support\LocalizedNumber;
use LynxCms\Core\Support\Value;

/**
 * What Settings > Backups shows: the backups, newest first, and how backups are made.
 *
 * @internal
 */
final readonly class BackupsView
{
    /** The backups the screen lists; older ones stay on the disks and in the table. */
    private const int LIMIT = 50;

    public function __construct(private BackupArchive $archive, private DumpBinaries $binaries, private ActorNames $actors) {}

    /** @return list<array{id: int, when: string, datetime: string, status: string, busy: bool, reason: string, actor: string, size: string|null, files: bool, error: string|null}> */
    public function rows(): array
    {
        return array_values(StoredBackup::query()->latest()->latest('id')->limit(self::LIMIT)->get()->map(fn (StoredBackup $backup): array => [
            'id' => $backup->id,
            'when' => $backup->created_at->isoFormat('LLL'),
            'datetime' => $backup->created_at->toIso8601String(),
            'status' => $backup->status->value,
            'busy' => in_array($backup->status, [BackupStatus::Queued, BackupStatus::Running], true),
            'reason' => $backup->reason,
            'actor' => $this->actor($backup->actor),
            'size' => $backup->size === null ? null : LocalizedNumber::fileSize($backup->size, 1),
            'files' => $backup->files,
            'error' => $backup->error,
        ])->all());
    }

    /** @return array{schedule: string, disks: list<string>, encrypted: bool, sqlite: bool} */
    public function summary(): array
    {
        return [
            'schedule' => $this->schedule(),
            'disks' => $this->archive->disks(),
            'encrypted' => Value::string(config('lynx-backup.password')) !== '',
            'sqlite' => $this->archive->driver() === 'sqlite',
        ];
    }

    /** The SQL dump is a choice only on SQLite, and only where sqlite3 runs. */
    public function offersDump(): bool
    {
        return $this->archive->driver() === 'sqlite' && $this->binaries->has('sqlite3');
    }

    private function schedule(): string
    {
        $when = Value::string(config('lynx-backup.schedule'));

        return match ($when) {
            '' => Value::string(__('lynx-backup::admin.schedule_off')),
            'daily' => Value::string(__('lynx-backup::admin.schedule_daily', ['time' => $this->dailyTime()])),
            default => Value::string(__('lynx-backup::admin.schedule_cron', ['cron' => $when])),
        };
    }

    /** The daily backup's time in the admin's locale: "02:00", "2:00 AM". */
    private function dailyTime(): string
    {
        $time = Carbon::createFromFormat('H:i', Value::string(config('lynx-backup.time'), '02:00'));

        return $time instanceof Carbon ? $time->isoFormat('LT') : Value::string(config('lynx-backup.time'));
    }

    /** "user:5" in words. */
    private function actor(string $key): string
    {
        [$kind, $id] = array_pad(explode(':', $key, 2), 2, null);

        return Value::string($this->actors->label((string) $kind, $id));
    }
}
