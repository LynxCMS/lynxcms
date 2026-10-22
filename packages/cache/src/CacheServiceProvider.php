<?php

declare(strict_types=1);

namespace LynxCms\Cache;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LynxCms\Admin\Navigation;
use LynxCms\Cache\Actions\ClearPageCache;
use LynxCms\Cache\Actions\InstallPageCache;
use LynxCms\Cache\Actions\SavePageCacheRules;
use LynxCms\Cache\Admin\AdminIntegration;
use LynxCms\Cache\Console\PageCacheCommand;
use LynxCms\Cache\Console\ServerRulesCommand;
use LynxCms\Cache\Fragments\FragmentClearer;
use LynxCms\Cache\Fragments\Fragments;
use LynxCms\Cache\Http\PageCacheMiddleware;
use LynxCms\Cache\Http\TokenRefreshController;
use LynxCms\Cache\Pages\CachePageStore;
use LynxCms\Cache\Pages\ClearOnChange;
use LynxCms\Cache\Pages\FilePageStore;
use LynxCms\Cache\Pages\Generation;
use LynxCms\Cache\Pages\PageCacheSettings;
use LynxCms\Cache\Pages\PageStore;
use LynxCms\Cache\Pages\RequestRules;
use LynxCms\Cache\View\IslandComponent;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Fields\DeleteField;
use LynxCms\Core\Actions\Fields\UpdateField;
use LynxCms\Core\Actions\Strings\SaveStrings;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Actions\Templates\DeleteTemplate;
use LynxCms\Core\Events\ContentChanged;
use LynxCms\Core\Facades\Lynx;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Lynx as CoreLynx;
use LynxCms\Core\Permissions\PermissionRegistry;
use LynxCms\Core\Support\Value;

/**
 * Registers the page cache (ADR-0064): the middleware, clearing on content changes, the
 * page_cache.* actions (and so their MCP and AI tools), lynx:page-cache and pruning.
 *
 * @api
 */
final class CacheServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    /** Each visit to a stored page with a token or islands makes one such request. */
    private const int DYNAMIC_REQUESTS_PER_MINUTE = 120;

    /** @var list<class-string<Action>> */
    private const array SCHEMA_ACTIONS = [CreateField::class, UpdateField::class, DeleteField::class, CreateTemplate::class, DeleteTemplate::class, SaveStrings::class];

    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT.'/config/lynx-cache.php', 'lynx-cache');
        $this->registerStores();
        $this->registerFragments();
        $this->app->bind(RequestRules::class, fn (Application $app): RequestRules => new RequestRules(
            $app->make(PageCacheSettings::class)->hosts(),
            $app->make(PageCacheSettings::class)->bypassCookies(),
            Value::strings(config('lynx-cache.pages.ignored_parameters')),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(self::ROOT.'/database/migrations');
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-cache');
        $this->registerRoutes();
        // The token and the refresh script, so scripts can always await window.lynxCsrfToken().
        Blade::component('lynx-island', IslandComponent::class);
        Blade::directive('lynxCache', fn (string $arguments): string => '<?php if (app(\\LynxCms\\Cache\\Fragments\\Fragments::class)->begin('.$arguments.')): ?>');
        Blade::directive('endLynxCache', fn (): string => '<?php endif; echo app(\\LynxCms\\Cache\\Fragments\\Fragments::class)->finish(); ?>');
        CoreLynx::macro('cache', fn (): Fragments => app(Fragments::class));
        Blade::directive('lynxCsrf', fn (): string => '<?php echo app(\\LynxCms\\Cache\\Pages\\TokenRefresh::class)->directive(csrf_token()); ?>');
        $this->app->make(ActionRegistry::class)->register(ClearPageCache::class, InstallPageCache::class, SavePageCacheRules::class);
        $permissions = $this->app->make(PermissionRegistry::class);
        $permissions->register('page_cache.manage', 'Manage the page cache', requires: ['pages.view']);
        $permissions->delegate(PageCacheSchema::SETTINGS_TEMPLATE, 'page_cache.manage');
        $this->registerClearing();
        $this->schedulePruning();

        if ($this->app->bound(Navigation::class)) {
            AdminIntegration::register();
        }
        // First in the stack, so stored pages are sent before the session starts.
        $this->app->make(Kernel::class)->prependMiddleware(PageCacheMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([self::ROOT.'/config/lynx-cache.php' => config_path('lynx-cache.php')], 'lynx-cache-config');
            $this->commands([PageCacheCommand::class, ServerRulesCommand::class]);
        }
    }

    private function registerClearing(): void
    {
        Event::listen(ContentChanged::class, [ClearOnChange::class, 'handle']);

        // These change what pages show without a ContentChanged event.
        foreach (self::SCHEMA_ACTIONS as $action) {
            Lynx::hook($action)->after(fn () => app(ClearOnChange::class)->clearAllAfterCommit());
        }
    }

    /** Registered at boot, before the core's catch-all page route. */
    private function registerRoutes(): void
    {
        Route::middleware(['web', 'throttle:'.self::DYNAMIC_REQUESTS_PER_MINUTE.',1'])->get('lynx-cache/dynamic', [TokenRefreshController::class, 'dynamic'])->name('lynx-cache.dynamic');
        Route::get('lynx-cache/refresh-token.js', [TokenRefreshController::class, 'script'])->name('lynx-cache.refresh-token.js');
    }

    /** Web servers keep sending expired files until they are deleted. */
    private function schedulePruning(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (config('lynx-cache.pages.enabled') === true) {
                $schedule->command('lynx:page-cache --prune')->everyMinute()->withoutOverlapping();
            }
        });
    }

    /** Bound, not shared: each one reads the config when it is made, so tests can change it. */
    private function registerStores(): void
    {
        $this->app->bind(Generation::class, fn (Application $app): Generation => new Generation($this->cacheStore($app)));
        $this->app->bind(FilePageStore::class, fn (Application $app): FilePageStore => new FilePageStore(
            $app->make(Filesystem::class),
            $app->make(Generation::class),
            rtrim(Value::string(config('lynx-cache.pages.path')), '/'),
        ));
        $this->app->bind(CachePageStore::class, fn (Application $app): CachePageStore => new CachePageStore($this->cacheStore($app), $app->make(Generation::class)));
        $this->app->bind(PageStore::class, fn (Application $app): PageStore => config('lynx-cache.pages.driver') === 'store'
            ? $app->make(CachePageStore::class)
            : $app->make(FilePageStore::class));
    }

    private function registerFragments(): void
    {
        $store = fn (Application $app): Repository => $app->make(CacheFactory::class)->store(Value::nullableString(config('lynx-cache.fragments.store')));
        $this->app->scoped(Fragments::class, fn (Application $app): Fragments => new Fragments($store($app), $app->make(Generation::class), $app->make(LanguageContext::class)));
        $this->app->bind(FragmentClearer::class, fn (Application $app): FragmentClearer => new FragmentClearer($store($app), $app->make(Generation::class)));
    }

    private function cacheStore(Application $app): Repository
    {
        return $app->make(CacheFactory::class)->store(Value::nullableString(config('lynx-cache.pages.store')));
    }
}
