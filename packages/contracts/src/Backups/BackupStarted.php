<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Backups;

/**
 * Fired when a database backup starts. Listeners can show that one is running; they must not wait
 * for it or order work by it.
 *
 * @api
 */
final readonly class BackupStarted
{
    public function __construct(public int $backup, public string $reason) {}
}
