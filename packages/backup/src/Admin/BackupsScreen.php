<?php

declare(strict_types=1);

namespace LynxCms\Backup\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Backup\Actions\CreateBackup;
use LynxCms\Backup\Actions\DeleteBackup;
use LynxCms\Backup\Actions\SaveBackupSettings;
use LynxCms\Backup\BackupSettings;
use LynxCms\Core\Support\Value;

/**
 * Settings > Backups (ADR-0158): the backups, Back up now, the owner's choices and deleting. It
 * checks on the screen every few seconds while a backup is queued or running.
 *
 * @internal
 */
final class BackupsScreen extends Component
{
    use Notifies;

    /** The last notice, for screen readers and tests (Notifies). */
    public ?string $status = null;

    public bool $includeFiles = false;

    public string $sqliteFormat = BackupSettings::COPY;

    /** Every request, updates included: they reach /livewire/update, outside the admin's routes and their gate. */
    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('backup.manage');
    }

    public function mount(BackupSettings $settings): void
    {
        $this->includeFiles = $settings->includesFiles();
        $this->sqliteFormat = $settings->sqliteFormat();
    }

    public function backUpNow(): void
    {
        lynx()->run(CreateBackup::class, ['reason' => Value::string(__('lynx-backup::admin.reason_now'))]);
        $this->notify(Value::string(__('lynx-backup::admin.queued')));
    }

    public function saveSettings(): void
    {
        lynx()->run(SaveBackupSettings::class, ['include_files' => $this->includeFiles, 'sqlite_format' => $this->sqliteFormat]);
        $this->notify(Value::string(__('lynx-backup::admin.settings_saved')));
    }

    public function delete(int $backup): void
    {
        lynx()->run(DeleteBackup::class, ['backup' => $backup]);
        $this->notify(Value::string(__('lynx-backup::admin.deleted')));
    }

    public function render(BackupsView $view): View
    {
        $rows = $view->rows();

        return view('lynx-backup::admin.screen', [
            'rows' => $rows,
            'summary' => $view->summary(),
            'offersDump' => $view->offersDump(),
            'isBusy' => array_any($rows, fn (array $row): bool => $row['busy']),
        ]);
    }
}
