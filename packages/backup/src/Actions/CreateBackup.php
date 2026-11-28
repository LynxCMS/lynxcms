<?php

declare(strict_types=1);

namespace LynxCms\Backup\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Backup\BackupArchive;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\Jobs\MakeBackup;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;

/**
 * Queue a database backup. A worker makes it; until then it is listed as queued.
 *
 * @api
 */
final class CreateBackup extends Action
{
    use FindsBackups;

    public function __construct(private readonly BackupArchive $archive) {}

    public static function name(): string
    {
        return 'backup.create';
    }

    public static function description(): string
    {
        return 'Queue a backup of the database. A worker makes it; until then it is listed as queued.';
    }

    public static function permission(): string
    {
        return 'backup.manage';
    }

    public static function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['reason' => $schema->string()->description('Why the backup is made, shown to the site\'s owner.')->required()];
    }

    public function handle(Input $input, ActionContext $context): StoredBackup
    {
        if (! $this->archive->isSupported()) {
            throw ValidationException::withMessages(['backup' => Value::string(__('lynx-backup::validation.unsupported'))]);
        }

        $backup = StoredBackup::query()->create([
            'reason' => (string) $input->string('reason'),
            'actor' => self::actorKey($context->actor),
            'status' => BackupStatus::Queued,
        ]);
        $context->changes->created('backup', $backup->id, "Queue a backup: {$backup->reason}");
        $actor = $context->actor;
        // Queued once the row commits, so the worker finds it; a dry run rolls back and queues nothing.
        DB::afterCommit(fn () => MakeBackup::dispatch($backup->id, $actor));

        return $backup;
    }
}
