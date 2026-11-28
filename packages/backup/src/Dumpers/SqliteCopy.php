<?php

declare(strict_types=1);

namespace LynxCms\Backup\Dumpers;

use LynxCms\Contracts\Backups\CouldNotBackUp;
use PDO;
use PDOException;
use Spatie\DbDumper\DbDumper;

/**
 * Copies an SQLite database with VACUUM INTO (ADR-0158): a consistent, compacted database file,
 * made through PDO without the sqlite3 binary. In WAL mode, writes go on while it copies.
 *
 * @internal
 */
final class SqliteCopy extends DbDumper
{
    public function dumpToFile(string $dumpFile): void
    {
        // PDO would create an empty database for a missing file, and back that up.
        if (! is_file($this->dbName)) {
            throw new CouldNotBackUp("The SQLite database [{$this->dbName}] does not exist.");
        }

        try {
            $database = new PDO('sqlite:'.$this->dbName, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $database->exec('VACUUM INTO '.$database->quote($dumpFile));
        } catch (PDOException $exception) {
            throw new CouldNotBackUp("VACUUM INTO failed for [{$this->dbName}]: {$exception->getMessage()}", $exception->getCode(), previous: $exception);
        }
    }
}
