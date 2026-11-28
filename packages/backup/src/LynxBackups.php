<?php

declare(strict_types=1);

namespace LynxCms\Backup;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Backup\Actions\FinishBackup;
use LynxCms\Backup\Actions\MarkRemovedBackups;
use LynxCms\Backup\Actions\StartBackup;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Contracts\Backups\BackupReference;
use LynxCms\Contracts\Backups\CouldNotBackUp;
use LynxCms\Contracts\Backups\DatabaseBackups;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use Throwable;

/**
 * The module's DatabaseBackups (ADR-0158). A backup is three steps: backup.start records it as
 * running, BackupArchive makes the zip outside any transaction (VACUUM INTO refuses to run inside
 * one, and a failure must stay recorded), and backup.finish records how it ended. The cleanup
 * follows a backup that worked.
 *
 * @internal
 */
final readonly class LynxBackups implements DatabaseBackups
{
    /** The longest error kept on a failed backup. */
    private const int ERROR_LIMIT = 2000;

    public function __construct(private ActionRunner $runner, private BackupArchive $archive) {}

    public function isAvailable(): bool
    {
        return $this->archive->isSupported() && $this->archive->disks() !== [];
    }

    public function create(string $reason): BackupReference
    {
        return $this->make(['reason' => $reason]);
    }

    /** Makes a backup that backup.create queued. */
    public function makeQueued(int $backup): BackupReference
    {
        return $this->make(['backup' => $backup]);
    }

    public function latest(): ?BackupReference
    {
        return $this->finished()->first()?->reference();
    }

    public function all(): array
    {
        return array_values($this->finished()->get()->map(fn (StoredBackup $backup): BackupReference => $backup->reference())->all());
    }

    /** @param  array<string, int|string>  $input */
    private function make(array $input): BackupReference
    {
        try {
            $backup = $this->runner->run(StartBackup::class, $input)->valueAs(StoredBackup::class);
        } catch (ValidationException $exception) {
            throw new CouldNotBackUp($exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        try {
            $path = $this->archive->make($backup->id);
        } catch (Throwable $exception) {
            $this->recordFailure($backup->id, $exception);

            throw new CouldNotBackUp("The backup failed: {$exception->getMessage()}", $exception->getCode(), previous: $exception);
        }

        $done = $this->runner->run(FinishBackup::class, ['backup' => $backup->id, 'path' => $path])->valueAs(StoredBackup::class);
        $this->cleanUp();

        return $done->reference();
    }

    /** The system records the failure, so it is kept whatever the permissions of whoever asked. */
    private function recordFailure(int $backup, Throwable $exception): void
    {
        $error = Str::limit($exception->getMessage(), self::ERROR_LIMIT);
        $this->runner->actingAs(Actor::system(), fn (): ActionResult => $this->runner->run(FinishBackup::class, ['backup' => $backup, 'error' => $error]));
    }

    /** Spatie's cleanup, then the rows of the zips it removed. A failed cleanup keeps the backup. */
    private function cleanUp(): void
    {
        try {
            $this->archive->cleanUp();
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->runner->actingAs(Actor::system(), fn (): ActionResult => $this->runner->run(MarkRemovedBackups::class));
    }

    /** @return Builder<StoredBackup> */
    private function finished(): Builder
    {
        return StoredBackup::query()->where('status', BackupStatus::Done)->latest()->latest('id');
    }
}
