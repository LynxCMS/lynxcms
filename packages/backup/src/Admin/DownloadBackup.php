<?php

declare(strict_types=1);

namespace LynxCms\Backup\Admin;

use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use LynxCms\Admin\AdminAccess;
use LynxCms\Backup\BackupStatus;
use LynxCms\Backup\Models\StoredBackup;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends a finished backup's zip from the first disk that has it, to backup.manage only, behind
 * password.confirm (AdminIntegration).
 *
 * @internal
 */
final readonly class DownloadBackup
{
    public function __construct(private AdminAccess $access, private Filesystems $filesystems) {}

    public function __invoke(int $backup): StreamedResponse
    {
        $this->access->authorizeScreen('backup.manage');
        $stored = StoredBackup::query()->where('status', BackupStatus::Done)->findOrFail($backup);

        foreach ($stored->disks ?? [] as $disk) {
            if ($this->filesystems->disk($disk)->exists((string) $stored->path)) {
                return $this->filesystems->disk($disk)->download((string) $stored->path);
            }
        }

        abort(404);
    }
}
