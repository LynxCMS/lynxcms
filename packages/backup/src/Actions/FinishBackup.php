<?php

declare(strict_types=1);

namespace LynxCms\Backup\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Backup\BackupArchive;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Contracts\Backups\BackupCompleted;
use LynxCms\Contracts\Backups\BackupFailed;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;

/**
 * Records how a backup ended: done with its zip's path and size, or failed with the error. A
 * queued backup whose job gave up can fail too. Not a tool: DatabaseBackups and the job run it.
 *
 * @internal
 */
final class FinishBackup extends Action
{
    use FindsBackups;

    public function __construct(private readonly BackupArchive $archive) {}

    public static function name(): string
    {
        return 'backup.finish';
    }

    public static function description(): string
    {
        return 'Record how a database backup ended: done with its zip, or failed with the error.';
    }

    public static function permission(): string
    {
        return 'backup.manage';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return [
            'backup' => ['required', 'integer'],
            'path' => ['required_without:error', 'string', 'max:255'],
            'error' => ['nullable', 'string'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'backup' => $schema->integer()->description('The id of the backup.')->required(),
            'path' => $schema->string()->description('The zip\'s path on each disk, when it is done.'),
            'error' => $schema->string()->description('Why it failed.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): StoredBackup
    {
        $backup = $this->backupIn($input->get('backup'), [BackupStatus::Queued, BackupStatus::Running]);
        $error = $input->string('error');

        if ($error !== null) {
            $backup->update(['status' => BackupStatus::Failed, 'error' => $error, 'finished_at' => now()]);
            $context->changes->updated('backup', $backup->id, "Backup failed: {$backup->reason}");
            $context->event(new BackupFailed($backup->id, $backup->reason, $error));

            return $backup;
        }

        $path = (string) $input->string('path');
        $backup->update(['status' => BackupStatus::Done, 'path' => $path, 'size' => $this->archive->size($path), 'finished_at' => now()]);
        $context->changes->updated('backup', $backup->id, "Backup done: {$backup->reason}");
        $context->event(new BackupCompleted($backup->reference()));

        return $backup;
    }
}
