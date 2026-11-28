<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Backups;

/**
 * Fired when a database backup is on every disk.
 *
 * @api
 */
final readonly class BackupCompleted
{
    public function __construct(public BackupReference $backup) {}
}
