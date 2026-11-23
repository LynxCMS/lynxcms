<?php

declare(strict_types=1);

namespace LynxCms\Core\Indexes;

use Illuminate\Support\ServiceProvider;
use LynxCms\Core\Actions\Runner\EventMuting;

/**
 * Indexes beside the field tables (ADR-0061): sort keys kept current by a listener, the
 * lynx:sort-keys and lynx:trigram commands.
 *
 * @internal
 */
final class IndexesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(EventMuting::class)->upkeep(UpdateSortKeys::EVENTS, UpdateSortKeys::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SortKeysCommand::class, TrigramCommand::class]);
        }
    }
}
