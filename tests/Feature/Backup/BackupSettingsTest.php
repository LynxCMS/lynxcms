<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Backup\Actions\SaveBackupSettings;
use LynxCms\Backup\BackupArchive;
use LynxCms\Backup\DumpBinaries;
use LynxCms\Contracts\Backups\DatabaseBackups;
use LynxCms\Tests\Fixtures\Backup\FixedBinaries;

/*
 * backup.save_settings stores the owner's choices (ADR-0158): files in each zip, and for SQLite a
 * copy or an SQL dump. A dump needs sqlite3, so it is refused where the binary is missing.
 */

beforeEach(function (): void {
    installLynx();
    backupSite();
    app()->instance(DumpBinaries::class, new FixedBinaries(['sqlite3']));
});

it('stores the owner\'s choices over the config defaults', function (): void {
    lynx()->run(SaveBackupSettings::class, ['include_files' => true, 'sqlite_format' => 'dump']);

    expect(app(BackupArchive::class)->includesFiles())->toBeTrue()
        ->and(app(BackupArchive::class)->format())->toBe(BackupArchive::SQLITE_DUMP);
});

it('backs up with the stored choices', function (): void {
    lynx()->run(SaveBackupSettings::class, ['include_files' => false, 'sqlite_format' => 'copy']);

    expect(array_keys(backupContents(app(DatabaseBackups::class)->create('Nightly')->path)))->toBe(['db-dumps/sqlitecopy-backup_site.sqlite']);
});

it('refuses an SQL dump where sqlite3 is missing', function (): void {
    app()->instance(DumpBinaries::class, new FixedBinaries([]));

    lynx()->run(SaveBackupSettings::class, ['include_files' => false, 'sqlite_format' => 'dump']);
})->throws(ValidationException::class);

it('refuses an unknown format', function (): void {
    lynx()->run(SaveBackupSettings::class, ['include_files' => false, 'sqlite_format' => 'mysqldump']);
})->throws(ValidationException::class);

it('changes nothing on a dry run', function (): void {
    lynx()->run(SaveBackupSettings::class, ['include_files' => true, 'sqlite_format' => 'copy'], dryRun: true);

    expect(app(BackupArchive::class)->includesFiles())->toBeFalse();
});
