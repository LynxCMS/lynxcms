<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures\Backup;

use LynxCms\Backup\DumpBinaries;

/** Dump binaries a test decides are installed. */
final readonly class FixedBinaries extends DumpBinaries
{
    /** @param  list<string>  $installed */
    public function __construct(private array $installed) {}

    public function has(string $binary): bool
    {
        return in_array($binary, $this->installed, true);
    }
}
