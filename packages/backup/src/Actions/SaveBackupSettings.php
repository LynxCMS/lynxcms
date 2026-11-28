<?php

declare(strict_types=1);

namespace LynxCms\Backup\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LynxCms\Backup\BackupSettings;
use LynxCms\Backup\DumpBinaries;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Models\Setting;
use LynxCms\Core\Support\Value;

/**
 * Save how backups are made: whether files go into each zip, and for SQLite a copy of the database
 * file (no binary needed) or an SQL dump (needs sqlite3).
 *
 * @api
 */
final class SaveBackupSettings extends Action
{
    public function __construct(private readonly DumpBinaries $binaries) {}

    public static function name(): string
    {
        return 'backup.save_settings';
    }

    public static function description(): string
    {
        return 'Save how database backups are made: whether files go into each zip, and for SQLite a copy of the database file or an SQL dump (needs sqlite3).';
    }

    public static function permission(): string
    {
        return 'backup.manage';
    }

    public static function rules(): array
    {
        return [
            'include_files' => ['required', 'boolean'],
            'sqlite_format' => ['required', Rule::in([BackupSettings::COPY, BackupSettings::DUMP])],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'include_files' => $schema->boolean()->description('Whether files go into each backup.')->required(),
            'sqlite_format' => $schema->string()->enum([BackupSettings::COPY, BackupSettings::DUMP])->description('SQLite only: "copy" (a database file) or "dump" (SQL text, needs sqlite3).')->required(),
        ];
    }

    /** @return array{include_files: bool, sqlite_format: string} */
    public function handle(Input $input, ActionContext $context): array
    {
        $settings = ['include_files' => $input->bool('include_files'), 'sqlite_format' => (string) $input->string('sqlite_format')];

        if ($settings['sqlite_format'] === BackupSettings::DUMP && ! $this->binaries->has('sqlite3')) {
            throw ValidationException::withMessages(['sqlite_format' => Value::string(__('lynx-backup::validation.no_sqlite3'))]);
        }

        Setting::write(BackupSettings::SETTING, json_encode($settings, JSON_THROW_ON_ERROR));
        $context->changes->updated('setting', null, 'Save the backup settings', $settings);

        return $settings;
    }
}
