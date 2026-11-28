<?php

declare(strict_types=1);

namespace LynxCms\Backup\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Backup\Actions\FinishBackup;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\LynxBackups;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Contracts\Backups\BackupReference;
use LynxCms\Contracts\Backups\CouldNotBackUp;
use LynxCms\Core\Actions\Runner\ActionResult;
use Throwable;

/**
 * Makes a backup that backup.create queued, as whoever asked for it. It is tried once: a failed
 * backup is recorded, and the owner asks again. A second try of the same job (the queue's
 * retry_after ran out during a long dump) finds the backup running and does nothing.
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

    /**
     * The queue gave up before the backup started: record why. A running backup is left alone,
     * since the queue also gives up when a long dump outlasts its retry_after and the job is handed
     * out again; that backup still ends on its own, and lynx:doctor reports one that never does.
     */
    public function failed(Throwable $exception): void
    {
        if (StoredBackup::query()->find($this->backup)?->status !== BackupStatus::Queued) {
            return;
        }

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
