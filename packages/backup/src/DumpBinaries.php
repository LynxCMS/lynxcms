<?php

declare(strict_types=1);

namespace LynxCms\Backup;

use LynxCms\Core\Support\Value;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Whether a dump binary (pg_dump, sqlite3) can run here: on the PATH, or in the folder the
 * connection's `dump.dump_binary_path` names, as Spatie's dumpers look for it.
 *
 * @internal
 */
readonly class DumpBinaries
{
    public function has(string $binary): bool
    {
        $connection = app(BackupArchive::class)->connection();
        $folder = Value::string(config("database.connections.{$connection}.dump.dump_binary_path"));

        return new ExecutableFinder()->find($binary, extraDirs: $folder === '' ? [] : [$folder]) !== null;
    }
}
