<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Backups;

use DateTimeImmutable;

/**
 * A finished database backup: one zip, at the same path on each of its disks.
 *
 * @api
 */
final readonly class BackupReference
{
    /**
     * @param  list<string>  $disks  the filesystem disks that hold the zip
     * @param  string  $path  the zip's path on each disk
     * @param  int  $size  the zip's size in bytes
     * @param  string  $actor  who asked: "system", "cli", or "user:{id}"
     */
    public function __construct(
        public int $id,
        public array $disks,
        public string $path,
        public int $size,
        public DateTimeImmutable $createdAt,
        public string $reason,
        public string $actor,
    ) {}
}
