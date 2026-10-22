<?php

declare(strict_types=1);

namespace LynxCms\Core\Indexes;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
        Event::listen(UpdateSortKeys::EVENTS, UpdateSortKeys::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SortKeysCommand::class, TrigramCommand::class]);
        }
    }
}
