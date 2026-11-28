<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Backups;

/**
 * Fired when a database backup fails. The error is for the site's owner and developers, in English.
 *
 * @api
 */
final readonly class BackupFailed
{
    public function __construct(public int $backup, public string $reason, public string $error) {}
}
