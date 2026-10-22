<?php

declare(strict_types=1);

namespace LynxCms\Core\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Search\RebuildSearchIndex;
use LynxCms\Core\Console\SearchIndexCommand;
use LynxCms\Core\Search\Engines\LikeSearchEngine;
use LynxCms\Core\Search\Engines\PostgresSearchEngine;
use LynxCms\Core\Search\Engines\SearchEngine;
use LynxCms\Core\Search\Engines\SqliteSearchEngine;

/**
 * The search index (ADR-0054): its engine for the connection, the listener keeping it current,
 * search.rebuild and lynx:search-index.
 *
 * @internal
 */
final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SearchEngine::class, function (): SearchEngine {
            $connection = DB::connection();

            return match ($connection->getDriverName()) {
                'sqlite' => new SqliteSearchEngine($connection),
                'pgsql' => new PostgresSearchEngine($connection),
                default => new LikeSearchEngine($connection),
            };
        });
    }

    public function boot(): void
    {
        $this->app->make(ActionRegistry::class)->register(RebuildSearchIndex::class);
        Event::listen(UpdateSearchIndex::EVENTS, UpdateSearchIndex::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SearchIndexCommand::class]);
        }
    }
}
