<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LynxCms\Backup\Admin\BackupsScreen;
use LynxCms\Backup\BackupArchive;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\DumpBinaries;
use LynxCms\Backup\Jobs\MakeBackup;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Contracts\Backups\DatabaseBackups;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Tests\Fixtures\Backup\FixedBinaries;

/*
 * Settings > Backups (ADR-0158): the backups with their size, age, reason and who asked, Back up
 * now, the owner's choices, deleting, and downloading after the password is confirmed again.
 * Only backup.manage, a superuser permission by default, opens it.
 */

beforeEach(function (): void {
    installLynx();
    backupSite();
    app()->instance(DumpBinaries::class, new FixedBinaries(['sqlite3']));
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('opens from the settings menu and lists the backups', function (): void {
    $backup = app(DatabaseBackups::class)->create('Before the upgrade');

    $this->get('/lynx')->assertOk()->assertSee('/lynx/backups', false);
    $this->get('/lynx/backups')->assertOk()->assertSee('data-backups', false)->assertSee("data-backup=\"{$backup->id}\"", false);
});

it('refuses users without backup.manage', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['editor']]);
    $this->actingAs(new GenericUser(['id' => 5]));

    $this->get('/lynx/backups')->assertForbidden();
    $this->get('/lynx')->assertOk()->assertDontSee('/lynx/backups', false);
});

it('queues a backup now', function (): void {
    Bus::fake();

    Livewire::test(BackupsScreen::class)->call('backUpNow')->assertHasNoErrors();

    Bus::assertDispatched(MakeBackup::class);
    expect(StoredBackup::query()->sole()->status)->toBe(BackupStatus::Queued);
});

it('shows a running backup and checks on it until it ends', function (): void {
    Bus::fake();
    Livewire::test(BackupsScreen::class)->call('backUpNow');

    Livewire::test(BackupsScreen::class)->assertSeeHtml('data-backup-status="queued"')->assertSeeHtml('wire:poll');
});

it('saves the owner\'s choices', function (): void {
    Livewire::test(BackupsScreen::class)
        ->set('includeFiles', true)
        ->set('sqliteFormat', 'dump')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect(app(BackupArchive::class)->includesFiles())->toBeTrue()
        ->and(app(BackupArchive::class)->format())->toBe(BackupArchive::SQLITE_DUMP);
});

it('offers the SQL dump only where sqlite3 is installed', function (): void {
    Livewire::test(BackupsScreen::class)->assertSeeHtml('data-sqlite-format');

    app()->instance(DumpBinaries::class, new FixedBinaries([]));

    Livewire::test(BackupsScreen::class)->assertDontSeeHtml('data-sqlite-format');
});

it('deletes a backup', function (): void {
    $backup = app(DatabaseBackups::class)->create('Old');

    Livewire::test(BackupsScreen::class)->call('delete', $backup->id)->assertHasNoErrors();

    expect(StoredBackup::query()->count())->toBe(0)
        ->and(Storage::disk('backups')->exists($backup->path))->toBeFalse();
});

it('downloads a backup after the password is confirmed again', function (): void {
    $backup = app(DatabaseBackups::class)->create('Nightly');

    $this->get("/lynx/backups/{$backup->id}/download")->assertRedirect();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->get("/lynx/backups/{$backup->id}/download")
        ->assertOk()
        ->assertDownload(basename($backup->path));
});

it('refuses to download for users without backup.manage', function (): void {
    $backup = app(DatabaseBackups::class)->create('Nightly');
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['editor']]);
    $this->actingAs(new GenericUser(['id' => 5]));

    $this->withSession(['auth.password_confirmed_at' => time()])->get("/lynx/backups/{$backup->id}/download")->assertForbidden();
});
