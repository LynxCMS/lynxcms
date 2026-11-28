<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures\Backup;

use LynxCms\Backup\FreeSpace;

/** The free space a test decides every disk has: bytes, or null when it cannot be measured. */
final readonly class FixedFreeSpace extends FreeSpace
{
    public function __construct(private ?float $bytes) {}

    public function on(string $disk): ?float
    {
        return $this->bytes;
    }
}
