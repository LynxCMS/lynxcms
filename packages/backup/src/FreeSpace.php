<?php

declare(strict_types=1);

namespace LynxCms\Backup;

use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Filesystem\FilesystemAdapter;
use LynxCms\Core\Support\Value;

/**
 * The free space on a backup disk, in bytes. Only local disks can say; a cloud disk has no limit
 * PHP can read.
 *
 * @internal
 */
readonly class FreeSpace
{
    public function __construct(private Filesystems $filesystems) {}

    public function on(string $disk): ?float
    {
        if (Value::string(config("filesystems.disks.{$disk}.driver")) !== 'local') {
            return null;
        }

        $filesystem = $this->filesystems->disk($disk);

        if (! $filesystem instanceof FilesystemAdapter) {
            return null;
        }

        $free = @disk_free_space($filesystem->path(''));

        return $free === false ? null : $free;
    }
}
