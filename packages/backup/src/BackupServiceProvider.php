<?php

declare(strict_types=1);

namespace LynxCms\Backup;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use LynxCms\Admin\Navigation;
use LynxCms\Backup\Actions\CreateBackup;
use LynxCms\Backup\Actions\DeleteBackup;
use LynxCms\Backup\Actions\FinishBackup;
use LynxCms\Backup\Actions\MarkRemovedBackups;
use LynxCms\Backup\Actions\SaveBackupSettings;
use LynxCms\Backup\Actions\StartBackup;
use LynxCms\Backup\Admin\AdminIntegration;
use LynxCms\Backup\Console\BackupDatabaseCommand;
use LynxCms\Backup\Doctor\BackupHealth;
use LynxCms\Contracts\Backups\DatabaseBackups;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Doctor\DoctorChecks;
use LynxCms\Core\Permissions\PermissionRegistry;
use LynxCms\Core\Support\Value;
use Override;
use Spatie\Backup\Notifications\EventHandler;

/**
 * The backup module (ADR-0158): database backups on spatie/laravel-backup, made daily and on
 * request, and DatabaseBackups for other modules.
 *
 * @internal
 */
final class BackupServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT.'/config/lynx-backup.php', 'lynx-backup');
        $this->app->singleton(DatabaseBackups::class, LynxBackups::class);
    }

    public function boot(): void
    {
        $this->app->make(ActionRegistry::class)->register(CreateBackup::class, DeleteBackup::class, StartBackup::class, FinishBackup::class, MarkRemovedBackups::class, SaveBackupSettings::class);
        // Superusers have every permission; no other role gets it unless the owner grants it, since
        // a backup holds every password hash and two-factor secret.
        $this->app->make(PermissionRegistry::class)->register('backup.manage', 'Make, download and delete database backups');
        $this->loadMigrationsFrom(self::ROOT.'/database/migrations');
        $this->loadTranslationsFrom(self::ROOT.'/lang', 'lynx-backup');
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-backup');
        $this->app->make(DoctorChecks::class)->add(BackupHealth::class);
        // Lynx reports backups through its own events and admin; Spatie's emails would go to its
        // placeholder address.
        EventHandler::disable();
        $this->scheduleBackups();

        if ($this->app->bound(Navigation::class)) {
            AdminIntegration::register();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([self::ROOT.'/config/lynx-backup.php' => config_path('lynx-backup.php')], 'lynx-backup-config');
            $this->commands([BackupDatabaseCommand::class]);
        }
    }

    private function scheduleBackups(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $when = Value::string(config('lynx-backup.schedule'));

            if ($when === '') {
                return;
            }

            $event = $schedule->command('lynx:backup:database', ['--reason' => 'Scheduled backup'])->withoutOverlapping();
            $when === 'daily' ? $event->dailyAt(Value::string(config('lynx-backup.time'), '02:00')) : $event->cron($when);
        });
    }
}
