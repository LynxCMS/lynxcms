<?php

declare(strict_types=1);

namespace LynxCms\Backup\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Backup\BackupArchive;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Contracts\Backups\BackupStarted;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;

/**
 * Marks a backup running before its zip is made: a queued one, or a new one for a backup made in
 * the calling process. Its own transaction, so the row shows while the dump runs. Not a tool:
 * DatabaseBackups runs it.
 *
 * @internal
 */
final class StartBackup extends Action
{
    use FindsBackups;

    public function __construct(private readonly BackupArchive $archive) {}

    public static function name(): string
    {
        return 'backup.start';
    }

    public static function description(): string
    {
        return 'Mark a database backup running, before its zip is made.';
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
        return ['backup' => ['nullable', 'integer'], 'reason' => ['required_without:backup', 'string', 'max:255']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'backup' => $schema->integer()->description('The id of a queued backup.'),
            'reason' => $schema->string()->description('Why a new backup is made.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): StoredBackup
    {
        if (! $this->archive->isSupported()) {
            throw ValidationException::withMessages(['backup' => Value::string(__('lynx-backup::validation.unsupported'))]);
        }

        $attributes = ['status' => BackupStatus::Running, 'format' => $this->archive->format(), 'disks' => $this->archive->disks(), 'files' => $this->archive->includesFiles()];
        $backup = $input->get('backup') !== null
            ? tap($this->backupIn($input->get('backup'), [BackupStatus::Queued]))->update($attributes)
            : StoredBackup::query()->create([...$attributes, 'reason' => (string) $input->string('reason'), 'actor' => self::actorKey($context->actor)]);
        $context->changes->updated('backup', $backup->id, "Start a backup: {$backup->reason}");
        $context->event(new BackupStarted($backup->id, $backup->reason));

        return $backup;
    }
}
