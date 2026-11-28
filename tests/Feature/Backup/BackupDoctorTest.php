<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use LynxCms\Backup\Actions\CreateBackup;
use LynxCms\Backup\Actions\SaveBackupSettings;
use LynxCms\Backup\Doctor\BackupHealth;
use LynxCms\Backup\DumpBinaries;
use LynxCms\Contracts\Backups\DatabaseBackups;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Tests\Fixtures\Backup\FixedBinaries;

/*
 * lynx:doctor reports what puts backups at risk (ADR-0158): no recent backup while they are
 * scheduled, a failed latest backup, a missing dump binary, one disk only, no archive password.
 */

beforeEach(function (): void {
    installLynx();
    backupSite();
    app()->instance(DumpBinaries::class, new FixedBinaries(['sqlite3', 'pg_dump']));
    config(['lynx-backup.disks' => ['backups', 'offsite'], 'lynx-backup.password' => 'secret']);
    Storage::fake('offsite');
});

/** @return list<string> */
function backupFindings(): array
{
    return array_map(fn (Finding $finding): string => $finding->message, app(BackupHealth::class)->run());
}

it('says nothing on a healthy setup', function (): void {
    app(DatabaseBackups::class)->create('Nightly');

    expect(app(BackupHealth::class)->run())->toBe([]);
});

it('warns once when no backup finished in the last 26 hours', function (): void {
    app(DatabaseBackups::class)->create('Nightly');
    $this->travel(27)->hours();

    expect(backupFindings())->toHaveCount(1)
        ->and(app(BackupHealth::class)->run()[0]->severity)->toBe(Severity::Warning);
});

it('warns when there is no backup yet', function (): void {
    expect(backupFindings())->toHaveCount(1);
});

it('says nothing about age when backups are not scheduled', function (): void {
    config(['lynx-backup.schedule' => '']);

    expect(backupFindings())->toBe([]);
});

it('reports a failed latest backup as an error', function (): void {
    app(DatabaseBackups::class)->create('Nightly');
    config(['database.connections.backup_site.database' => '/missing/site.sqlite']);
    rescue(fn () => app(DatabaseBackups::class)->create('Nightly'), report: false);

    $findings = app(BackupHealth::class)->run();

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity)->toBe(Severity::Error);
});

it('reports a missing sqlite3 only when SQLite is dumped', function (): void {
    app(DatabaseBackups::class)->create('Nightly');
    lynx()->run(SaveBackupSettings::class, ['include_files' => false, 'sqlite_format' => 'dump']);
    app()->instance(DumpBinaries::class, new FixedBinaries([]));

    expect(backupFindings())->toHaveCount(1);

    lynx()->run(SaveBackupSettings::class, ['include_files' => false, 'sqlite_format' => 'copy']);

    expect(backupFindings())->toBe([]);
});

it('reports a missing pg_dump for PostgreSQL', function (): void {
    app(DatabaseBackups::class)->create('Nightly');
    config(['database.connections.backup_site.driver' => 'pgsql']);
    app()->instance(DumpBinaries::class, new FixedBinaries([]));

    expect(collect(backupFindings())->filter(fn (string $message): bool => str_contains($message, 'pg_dump')))->toHaveCount(1);
});

it('warns about one disk only and about no password', function (): void {
    app(DatabaseBackups::class)->create('Nightly');
    config(['lynx-backup.disks' => ['backups'], 'lynx-backup.password' => null]);

    expect(backupFindings())->toHaveCount(2);
});

it('reports a database Lynx cannot back up', function (): void {
    config(['database.connections.backup_site' => ['driver' => 'sqlsrv', 'database' => 'site']]);

    expect(app(BackupHealth::class)->run()[0]->severity)->toBe(Severity::Error);
});

it('warns about a backup queued or running for more than 30 minutes', function (): void {
    app(DatabaseBackups::class)->create('Nightly');
    Bus::fake();
    lynx()->run(CreateBackup::class, ['reason' => 'From the admin']);

    expect(backupFindings())->toBe([]);

    $this->travel(31)->minutes();

    expect(collect(backupFindings())->filter(fn (string $message): bool => str_contains($message, 'queue')))->toHaveCount(1);
});
