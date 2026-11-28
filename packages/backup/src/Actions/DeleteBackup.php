<?php

declare(strict_types=1);

namespace LynxCms\Backup\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use LynxCms\Backup\BackupArchive;
use LynxCms\Backup\BackupStatus;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;

/**
 * Delete a backup: its zip on every disk, and its row. A queued or running backup is refused.
 *
 * @api
 */
final class DeleteBackup extends Action
{
    use FindsBackups;

    public function __construct(private readonly BackupArchive $archive) {}

    public static function name(): string
    {
        return 'backup.delete';
    }

    public static function description(): string
    {
        return 'Delete a database backup: its zip on every disk, and its row. A queued or running backup is refused.';
    }

    public static function permission(): string
    {
        return 'backup.manage';
    }

    public static function destructive(): bool
    {
        return true;
    }

    public static function rules(): array
    {
        return ['backup' => ['required', 'integer']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['backup' => $schema->integer()->description('The id of the backup.')->required()];
    }

    public function handle(Input $input, ActionContext $context): int
    {
        $backup = $this->backupIn($input->get('backup'), [BackupStatus::Done, BackupStatus::Failed, BackupStatus::Removed]);
        $path = $backup->path;
        $disks = $backup->disks ?? [];
        $backup->delete();
        $context->changes->deleted('backup', $backup->id, "Delete the backup: {$backup->reason}");

        if ($path !== null) {
            // The files go once the row's deletion commits; a dry run keeps both.
            DB::afterCommit(fn () => $this->archive->delete($path, $disks));
        }

        return $backup->id;
    }
}
