<?php

declare(strict_types=1);

namespace LynxCms\Backup\Models;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\BackupTables;
use LynxCms\Contracts\Backups\BackupReference;
use Override;

/**
 * One backup: why and by whom it was made, its zip and how it ended. A module system table,
 * written only through the backup actions.
 *
 * @property int $id
 * @property string $reason
 * @property string $actor
 * @property BackupStatus $status
 * @property string|null $format
 * @property list<string>|null $disks
 * @property string|null $path
 * @property int|null $size
 * @property bool $files
 * @property string|null $error
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @internal
 */
final class StoredBackup extends Model
{
    #[Override]
    protected $table = BackupTables::BACKUPS;

    #[Override]
    protected $guarded = [];

    /** The finished backup as other code sees it. */
    public function reference(): BackupReference
    {
        return new BackupReference(
            id: $this->id,
            disks: $this->disks ?? [],
            path: (string) $this->path,
            size: (int) $this->size,
            createdAt: DateTimeImmutable::createFromInterface($this->created_at),
            reason: $this->reason,
            actor: $this->actor,
        );
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['status' => BackupStatus::class, 'disks' => 'array', 'size' => 'integer', 'files' => 'boolean', 'finished_at' => 'datetime'];
    }
}
