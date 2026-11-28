<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LynxCms\Backup\Actions\CreateBackup;
use LynxCms\Backup\Actions\DeleteBackup;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\Jobs\MakeBackup;
use LynxCms\Backup\Models\StoredBackup;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Contracts\Backups\DatabaseBackups;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;

/*
 * backup.create queues a backup, backup.delete removes one, and the schedule makes one daily
 * (ADR-0158). Only superusers have backup.manage by default: a backup holds every password hash.
 */

beforeEach(function (): void {
    installLynx();
    backupSite();
});

function queueBackup(string $reason = 'Before a big change'): StoredBackup
{
    return lynx()->run(CreateBackup::class, ['reason' => $reason])->valueAs(StoredBackup::class);
}

it('queues a backup and makes it on the queue', function (): void {
    Bus::fake();

    $backup = queueBackup();

    expect($backup->status)->toBe(BackupStatus::Queued);
    Bus::assertDispatched(MakeBackup::class, function (MakeBackup $job) use ($backup): bool {
        $job->handle();

        return $job->backup === $backup->id;
    });
    expect($backup->fresh()?->status)->toBe(BackupStatus::Done)
        ->and(Storage::disk('backups')->exists((string) $backup->fresh()?->path))->toBeTrue();
});

it('queues nothing on a dry run', function (): void {
    Bus::fake();

    lynx()->run(CreateBackup::class, ['reason' => 'Before a big change'], dryRun: true);

    Bus::assertNotDispatched(MakeBackup::class);
    expect(StoredBackup::query()->count())->toBe(0);
});

it('refuses editors without backup.manage', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['editor']]);

    expect(fn (): mixed => lynx()->actingAs(Actor::user(2), fn (): ActionResult => lynx()->run(CreateBackup::class, ['reason' => 'Mine'])))
        ->toThrow(AuthorizationException::class);
});

it('lets the system make a backup', function (): void {
    Bus::fake();

    lynx()->actingAs(Actor::system(), fn (): StoredBackup => queueBackup());

    Bus::assertDispatched(MakeBackup::class);
});

it('marks a queued backup failed when its job gives up', function (): void {
    Bus::fake();
    $backup = queueBackup();

    new MakeBackup($backup->id, Actor::cli())->failed(new RuntimeException('The worker timed out.'));

    expect($backup->fresh()?->status)->toBe(BackupStatus::Failed)
        ->and($backup->fresh()?->error)->toBe('The worker timed out.');
});

it('deletes a backup and its file', function (): void {
    $backup = app(DatabaseBackups::class)->create('Old');

    lynx()->run(DeleteBackup::class, ['backup' => $backup->id]);

    expect(StoredBackup::query()->count())->toBe(0)
        ->and(Storage::disk('backups')->exists($backup->path))->toBeFalse();
});

it('refuses to delete a backup that is still running', function (): void {
    Bus::fake();

    lynx()->run(DeleteBackup::class, ['backup' => queueBackup()->id]);
})->throws(ValidationException::class);

it('marks the backups whose files the cleanup removed', function (): void {
    $kept = app(DatabaseBackups::class)->create('Kept');
    $this->travel(2)->seconds();
    $removed = app(DatabaseBackups::class)->create('Removed');
    Storage::disk('backups')->delete($removed->path);

    app(DatabaseBackups::class)->create('Latest');

    expect(StoredBackup::query()->findOrFail($removed->id)->status)->toBe(BackupStatus::Removed)
        ->and(StoredBackup::query()->findOrFail($kept->id)->status)->toBe(BackupStatus::Done);
});

it('makes a backup with lynx:backup:database', function (): void {
    $this->artisan('lynx:backup:database', ['--reason' => 'By hand'])->assertSuccessful();

    expect(StoredBackup::query()->sole()->reason)->toBe('By hand');
});

/** @return list<ScheduledEvent> */
function backupEvents(): array
{
    app()->forgetInstance(Schedule::class);

    return array_values(array_filter(app(Schedule::class)->events(), fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'lynx:backup:database')));
}

it('backs up daily at 02:00 by default', function (): void {
    expect(backupEvents())->toHaveCount(1)
        ->and(backupEvents()[0]->expression)->toBe('0 2 * * *');
});

it('follows the schedule the owner sets, and none when it is empty', function (): void {
    config(['lynx-backup.schedule' => '30 3 * * 0']);
    expect(backupEvents()[0]->expression)->toBe('30 3 * * 0');

    config(['lynx-backup.schedule' => '']);
    expect(backupEvents())->toBe([]);
});
