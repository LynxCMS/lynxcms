<?php

declare(strict_types=1);

namespace LynxCms\Backup;

/**
 * Where a backup is: queued for a worker, running, done, failed, or removed by the cleanup.
 *
 * @internal
 */
enum BackupStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
    case Removed = 'removed';
}
