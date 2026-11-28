<?php

declare(strict_types=1);

namespace LynxCms\Backup\Actions;

use Illuminate\Validation\ValidationException;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Support\Value;

/**
 * What the backup actions share: finding a backup in a given status, and how an actor is stored.
 *
 * @internal
 */
trait FindsBackups
{
    /** How an actor is stored: "user:5", "cli", "system". */
    private static function actorKey(Actor $actor): string
    {
        return $actor->kind.($actor->id !== null ? ':'.$actor->id : '');
    }

    /** @param  list<BackupStatus>  $statuses */
    private function backupIn(mixed $id, array $statuses): StoredBackup
    {
        $backup = StoredBackup::query()->find(Value::int($id));

        if ($backup === null || ! in_array($backup->status, $statuses, true)) {
            throw ValidationException::withMessages(['backup' => Value::string(__('lynx-backup::validation.not_found'))]);
        }

        return $backup;
    }
}
