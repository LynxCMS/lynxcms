<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Contracts\Backups\BackupCompleted;
use LynxCms\Contracts\Backups\BackupFailed;
use LynxCms\Contracts\Backups\BackupReference;
use LynxCms\Contracts\Backups\BackupStarted;
use LynxCms\Contracts\Backups\CouldNotBackUp;
use LynxCms\Contracts\Backups\DatabaseBackups;

/*
 * DatabaseBackups (ADR-0158): a backup made in the calling process, as a zip on the configured
 * disks, with a row saying why and by whom. SQLite is copied with VACUUM INTO by default.
 */

beforeEach(function (): void {
    installLynx();
    $this->database = backupSite();
    $this->backups = app(DatabaseBackups::class);
});

it('copies an SQLite database into a zip on the backup disk', function (): void {
    $backup = $this->backups->create('Before the upgrade');
    $files = backupContents($backup->path);

    expect($backup->disks)->toBe(['backups'])
        ->and(Storage::disk('backups')->exists($backup->path))->toBeTrue()
        ->and($backup->size)->toBe(Storage::disk('backups')->size($backup->path))
        ->and(array_keys($files))->toBe(['db-dumps/sqlitecopy-backup_site.sqlite'])
        ->and(notesIn($files['db-dumps/sqlitecopy-backup_site.sqlite']))->toBe(3);
});

it('records the backup with its reason and who asked', function (): void {
    $backup = $this->backups->create('Before the upgrade');
    $row = StoredBackup::query()->findOrFail($backup->id);

    expect($row->status)->toBe(BackupStatus::Done)
        ->and($row->reason)->toBe('Before the upgrade')
        ->and($row->actor)->toBe('cli')
        ->and($row->format)->toBe('sqlite-copy')
        ->and($row->size)->toBe($backup->size)
        ->and($backup->reason)->toBe('Before the upgrade')
        ->and($backup->actor)->toBe('cli');
});

it('reports the start and the end of a backup', function (): void {
    Event::fake([BackupStarted::class, BackupCompleted::class, BackupFailed::class]);

    $backup = $this->backups->create('Nightly');

    Event::assertDispatched(BackupStarted::class, fn (BackupStarted $event): bool => $event->backup === $backup->id && $event->reason === 'Nightly');
    Event::assertDispatched(BackupCompleted::class, fn (BackupCompleted $event): bool => $event->backup == $backup);
    Event::assertNotDispatched(BackupFailed::class);
});

it('dumps SQLite as SQL text when the owner chooses it and sqlite3 is installed', function (): void {
    config(['lynx-backup.sqlite_format' => 'dump']);

    $backup = $this->backups->create('Nightly');

    expect(array_keys(backupContents($backup->path)))->toBe(['db-dumps/sqlite-backup_site.sql'])
        ->and(StoredBackup::query()->findOrFail($backup->id)->format)->toBe('sqlite-dump');
})->skip(fn (): bool => ! Process::run('sqlite3 --version')->successful(), 'sqlite3 is not installed.');

it('records a failed backup, reports it and throws', function (): void {
    Event::fake([BackupFailed::class]);
    unlink($this->database);
    config(['database.connections.backup_site.database' => $this->database.'-missing/site.sqlite']);

    expect(fn (): BackupReference => $this->backups->create('Nightly'))->toThrow(CouldNotBackUp::class);

    $row = StoredBackup::query()->sole();
    expect($row->status)->toBe(BackupStatus::Failed)
        ->and($row->error)->not->toBe('');
    Event::assertDispatched(BackupFailed::class, fn (BackupFailed $event): bool => $event->backup === $row->id);
});

it('backs up only SQLite and PostgreSQL', function (): void {
    config(['database.connections.backup_site' => ['driver' => 'mysql', 'database' => 'site']]);

    expect($this->backups->isAvailable())->toBeFalse()
        ->and(fn (): BackupReference => $this->backups->create('Nightly'))->toThrow(CouldNotBackUp::class)
        ->and(StoredBackup::query()->count())->toBe(0);
});

it('lists the finished backups, newest first', function (): void {
    $first = $this->backups->create('First');
    $this->travel(2)->seconds();
    $second = $this->backups->create('Second');

    expect($this->backups->latest()?->id)->toBe($second->id)
        ->and(array_map(fn (BackupReference $backup): int => $backup->id, $this->backups->all()))->toBe([$second->id, $first->id]);
});

it('includes the files when the owner asks for them', function (): void {
    $files = testingPath('backup').'/files';
    mkdir($files);
    file_put_contents($files.'/logo.svg', '<svg/>');
    config(['lynx-backup.include_files' => true, 'lynx-backup.files' => [$files]]);

    $backup = $this->backups->create('Nightly');

    expect(collect(array_keys(backupContents($backup->path)))->contains(fn (string $name): bool => str_ends_with($name, 'logo.svg')))->toBeTrue();
});
