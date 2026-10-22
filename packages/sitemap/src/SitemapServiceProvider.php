<?php

declare(strict_types=1);

namespace LynxCms\Sitemap;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Events\ContentChanged;
use LynxCms\Core\Lynx;
use LynxCms\Core\Permissions\PermissionRegistry;
use LynxCms\Seo\SeoSchema;
use LynxCms\Sitemap\Actions\ClearSitemapCache;
use LynxCms\Sitemap\Actions\InstallSitemap;
use LynxCms\Sitemap\Admin\AdminIntegration;
use LynxCms\Sitemap\Console\SitemapCommand;
use LynxCms\Sitemap\Http\SitemapController;
use LynxCms\Sitemap\Integrations\SeoIntegration;

/**
 * Registers the sitemap (ADR-0057): /sitemap.xml and its parts, lynx()->sitemap(), the actions
 * (and so their MCP and AI tools), lynx:sitemap and the sitemap.manage permission.
 *
 * @api
 */
final class SitemapServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT.'/config/lynx-sitemap.php', 'lynx-sitemap');
        $this->app->singleton(SitemapFilters::class);
    }

    public function boot(): void
    {
        $this->app->make(ActionRegistry::class)->register(InstallSitemap::class, ClearSitemapCache::class);
        $permissions = $this->app->make(PermissionRegistry::class);
        $permissions->register('sitemap.manage', 'Manage the sitemap', requires: ['pages.view']);
        $permissions->delegate(SitemapSchema::SETTINGS_TEMPLATE, 'sitemap.manage');
        Lynx::macro('sitemap', fn (): Sitemap => app(Sitemap::class));
        // Any content change may change the sitemap: build it again on the next request.
        Event::listen(ContentChanged::class, fn () => app(SitemapCache::class)->clear());
        $this->registerRoutes();
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-sitemap');

        if (class_exists(SeoSchema::class)) {
            SeoIntegration::register();
        }

        if ($this->app->bound(Navigation::class)) {
            AdminIntegration::register();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SitemapCommand::class]);
            $this->publishes([self::ROOT.'/config/lynx-sitemap.php' => config_path('lynx-sitemap.php')], 'lynx-sitemap-config');
        }
    }

    /** Registered before Lynx's catch-all page route, which is added once the app has booted. */
    private function registerRoutes(): void
    {
        Route::get('sitemap.xml', [SitemapController::class, 'root'])->name('lynx.sitemap');
        Route::get('sitemap-{part}.xml', [SitemapController::class, 'part'])->whereNumber('part')->name('lynx.sitemap.part');
        Route::get('sitemap-{template}-{part}.xml', [SitemapController::class, 'templatePart'])
            ->where(['template' => '[a-z][a-z0-9_-]*', 'part' => '[0-9]+'])->name('lynx.sitemap.template');
    }
}
