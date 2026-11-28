<?php

declare(strict_types=1);

namespace LynxCms\Backup\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Backup\Actions\FinishBackup;
use LynxCms\Backup\LynxBackups;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Contracts\Backups\BackupReference;
use LynxCms\Contracts\Backups\CouldNotBackUp;
use LynxCms\Core\Actions\Runner\ActionResult;
use Throwable;

/**
 * Makes a backup that backup.create queued, as whoever asked for it. It is tried once: a failed
 * backup is recorded, and the owner asks again. When the worker gives up on it (its timeout, a
 * lost connection), failed() records the failure, so the backup does not stay queued.
 *
 * @internal
 */
final class MakeBackup implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** A large database takes a while to dump and copy to an off-site disk. */
    public int $timeout = 3600;

    public function __construct(public readonly int $backup, public readonly Actor $actor) {}

    public function handle(): void
    {
        try {
            lynx()->actingAs($this->actor, fn (): BackupReference => app(LynxBackups::class)->makeQueued($this->backup));
        } catch (CouldNotBackUp $exception) {
            // Recorded on the backup and reported by BackupFailed; the job itself did its work.
            report($exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            lynx()->actingAs(Actor::system(), fn (): ActionResult => lynx()->run(FinishBackup::class, [
                'backup' => $this->backup,
                'error' => Str::limit($exception->getMessage(), 2000),
            ]));
        } catch (ValidationException) {
            // The backup ended already.
            return;
        }
    }
}
