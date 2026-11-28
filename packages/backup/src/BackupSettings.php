<?php

declare(strict_types=1);

namespace LynxCms\Backup;

use LynxCms\Core\Models\Setting;
use LynxCms\Core\Support\Value;

/**
 * The owner's backup choices from Settings > Backups (ADR-0158), saved by backup.save_settings:
 * whether files go into each zip, and how SQLite is backed up. Until the owner saves them, the
 * config's defaults apply.
 *
 * @internal
 */
final readonly class BackupSettings
{
    public const string SETTING = 'backup.settings';

    public const string COPY = 'copy';

    public const string DUMP = 'dump';

    public function includesFiles(): bool
    {
        return Value::bool($this->stored()['include_files'] ?? config('lynx-backup.include_files'));
    }

    public function sqliteFormat(): string
    {
        return Value::string($this->stored()['sqlite_format'] ?? config('lynx-backup.sqlite_format')) === self::DUMP ? self::DUMP : self::COPY;
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        return Value::map(json_decode((string) Setting::read(self::SETTING), true));
    }
}
