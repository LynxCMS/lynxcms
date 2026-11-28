<?php

declare(strict_types=1);

namespace LynxCms\Backup\Admin;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Navigation;

/**
 * Adds Settings > Backups to the admin, for backup.manage. Downloading a backup asks for the
 * password again: a zip holds every password hash and two-factor secret. Called only when
 * lynxcms/admin is installed.
 *
 * @internal
 */
final class AdminIntegration
{
    private const int NAVIGATION_ORDER = 65;

    public static function register(): void
    {
        Livewire::component('lynx-backup.screen', BackupsScreen::class);
        $canManage = fn (): bool => app(AdminAccess::class)->allows('backup.manage');
        app(Navigation::class)->add('lynx-backup::admin.title', 'lynx.admin.backups', order: self::NAVIGATION_ORDER, visible: $canManage, icon: 'database-backup', group: Navigation::SETTINGS);
        Admin::routes(function (): void {
            Admin::screen('backups', 'lynx-backup::admin.index', 'backup.manage')->name('backups');
            Route::get('backups/{backup}/download', DownloadBackup::class)->whereNumber('backup')->middleware('password.confirm')->name('backups.download');
        });
    }
}
