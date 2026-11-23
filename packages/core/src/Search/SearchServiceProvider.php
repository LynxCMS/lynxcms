<?php

declare(strict_types=1);

namespace LynxCms\Core\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Runner\EventMuting;
use LynxCms\Core\Actions\Search\RebuildSearchIndex;
use LynxCms\Core\Agents\ReadToolRegistry;
use LynxCms\Core\Console\SearchIndexCommand;
use LynxCms\Core\Search\Engines\LikeSearchEngine;
use LynxCms\Core\Search\Engines\PostgresSearchEngine;
use LynxCms\Core\Search\Engines\SearchEngine;
use LynxCms\Core\Search\Engines\SqliteSearchEngine;
use LynxCms\Core\Search\Plurals\DutchPlurals;
use LynxCms\Core\Search\Plurals\EnglishPlurals;
use LynxCms\Core\Search\Plurals\FrenchPlurals;
use LynxCms\Core\Search\Plurals\GermanPlurals;
use LynxCms\Core\Search\Plurals\ItalianPlurals;
use LynxCms\Core\Search\Plurals\PortuguesePlurals;
use LynxCms\Core\Search\Plurals\SpanishPlurals;
use LynxCms\Core\Search\Plurals\WordReducers;

/**
 * The search index (ADR-0054): its engine for the connection, the plural reducers (ADR-0119),
 * the listener keeping it current, search.rebuild, search_inspect and lynx:search-index.
 *
 * @internal
 */
final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WordReducers::class, function (): WordReducers {
            $reducers = new WordReducers;
            $reducers->register('pt', new PortuguesePlurals);
            $reducers->register('en', new EnglishPlurals);
            $reducers->register('fr', new FrenchPlurals);
            $reducers->register('es', new SpanishPlurals);
            $reducers->register('it', new ItalianPlurals);
            $reducers->register('de', new GermanPlurals);
            $reducers->register('nl', new DutchPlurals);

            return $reducers;
        });

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
        $this->app->make(ReadToolRegistry::class)->register(SearchInspectTool::class);
        $this->app->make(EventMuting::class)->upkeep(UpdateSearchIndex::EVENTS, UpdateSearchIndex::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SearchIndexCommand::class]);
        }
    }
}
