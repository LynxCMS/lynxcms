<?php

declare(strict_types=1);

namespace LynxCms\Backup\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Backup\BackupArchive;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;

/**
 * Marks the finished backups whose zips are on no disk any more, after Spatie's cleanup removed
 * them (or someone did by hand). Not a tool: the backup runs it after each cleanup.
 *
 * @internal
 */
final class MarkRemovedBackups extends Action
{
    public function __construct(private readonly BackupArchive $archive) {}

    public static function name(): string
    {
        return 'backup.mark_removed';
    }

    public static function description(): string
    {
        return 'Mark the database backups whose zips are on no disk any more.';
    }

    public static function permission(): string
    {
        return 'backup.manage';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function logsActivity(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return [];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    /** @return list<int> the ids of the backups marked removed */
    public function handle(Input $input, ActionContext $context): array
    {
        $removed = [];

        foreach (StoredBackup::query()->where('status', BackupStatus::Done)->get() as $backup) {
            if (! $this->archive->exists((string) $backup->path, $backup->disks ?? [])) {
                $backup->update(['status' => BackupStatus::Removed]);
                $context->changes->updated('backup', $backup->id, "Backup removed by the cleanup: {$backup->reason}");
                $removed[] = $backup->id;
            }
        }

        return $removed;
    }
}
