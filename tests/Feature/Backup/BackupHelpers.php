<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
 * Backup tests back up a small SQLite file of their own (the `backup_site` connection): the test
 * database is in memory, or PostgreSQL inside a transaction, and VACUUM INTO needs neither.
 */

/** Points the backup module at a fresh SQLite file with three rows, and fakes the backup disk. */
function backupSite(): string
{
    $folder = testingPath('backup');
    File::deleteDirectory($folder);
    File::ensureDirectoryExists($folder);
    $database = $folder.'/site.sqlite';
    touch($database);

    config([
        'database.connections.backup_site' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true],
        'lynx-backup.connection' => 'backup_site',
        'lynx-backup.disks' => ['backups'],
        'backup.backup.temporary_directory' => $folder.'/temp',
    ]);
    Storage::fake('backups');

    $site = DB::connection('backup_site');
    $site->statement('create table notes (id integer primary key, body text)');
    $site->table('notes')->insert([['body' => 'one'], ['body' => 'two'], ['body' => 'three']]);

    return $database;
}

/**
 * The files inside a backup's zip, extracted to a folder.
 *
 * @return array<string, string> the path inside the zip => the extracted file
 */
function backupContents(string $path): array
{
    $folder = testingPath('backup').'/extracted';
    File::deleteDirectory($folder);
    $zip = new ZipArchive;
    $zip->open(Storage::disk('backups')->path($path));
    $zip->extractTo($folder);
    $files = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = (string) $zip->getNameIndex($index);
        $files[$name] = $folder.'/'.$name;
    }

    $zip->close();

    return $files;
}

/** The rows of the notes table in an SQLite file. */
function notesIn(string $database): int
{
    return (int) new PDO('sqlite:'.$database)->query('select count(*) from notes')?->fetchColumn();
}
