<?php

declare(strict_types=1);

namespace LynxCms\Core\Indexes;

use Illuminate\Console\Command;
use Override;

/** Writes every sort key again, after changing lynx.sort_keys or a field's fallback setting. */
final class SortKeysCommand extends Command
{
    #[Override]
    protected $signature = 'lynx:sort-keys';

    #[Override]
    protected $description = 'Rebuild the sort keys of translatable fields';

    public function handle(SortKeys $keys): int
    {
        $this->components->info('Wrote the sort keys of '.$keys->rebuild().' page(s).');

        return self::SUCCESS;
    }
}
