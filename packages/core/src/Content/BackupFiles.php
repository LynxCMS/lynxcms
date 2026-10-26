<?php

declare(strict_types=1);

namespace LynxCms\Core\Content;

use Illuminate\Support\Facades\File;
use LynxCms\Core\Support\Value;

/**
 * Where lynx:backup writes its zips (lynx.backups.path), and the zips there, newest first. Their
 * names carry the date, so sorting by name sorts by age.
 *
 * @internal
 */
final class BackupFiles
{
    public static function directory(): string
    {
        return Value::string(config('lynx.backups.path')) ?: storage_path('app/lynx/backups');
    }

    /** @return list<string> */
    public static function latestFirst(): array
    {
        $backups = Value::strings(File::glob(self::directory().'/lynx-*.zip') ?: []);
        rsort($backups);

        return $backups;
    }
}
