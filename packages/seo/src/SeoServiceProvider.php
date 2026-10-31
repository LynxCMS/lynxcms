<?php

declare(strict_types=1);

namespace LynxCms\Seo;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\HookRegistry;
use LynxCms\Core\Agents\ReadToolRegistry;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Permissions\PermissionRegistry;
use LynxCms\Core\Support\Value;
use LynxCms\Seo\Actions\DisableSeo;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\Actions\InstallSeo;
use LynxCms\Seo\Actions\MapTemplate;
use LynxCms\Seo\Actions\UnmapTemplate;
use LynxCms\Seo\Admin\AdminIntegration;
use LynxCms\Seo\Agents\InspectSeoTool;
use LynxCms\Seo\Http\RobotsController;
use LynxCms\Seo\JsonLd\JsonLd;
use LynxCms\Seo\JsonLd\MappingValidator;
use LynxCms\Seo\View\SeoComponent;
use LynxCms\Seo\View\SeoHead;

/**
 * Registers SEO (ADR-0055): the actions (and so their MCP and AI tools), lynx()->seo($page),
 * <x-lynx-seo> and @seo with JSON-LD, /robots.txt, the seo_inspect read tool and the seo.manage permission.
 *
 * @api
 */
final class SeoServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT.'/config/lynx-seo.php', 'lynx-seo');
        $this->app->singleton(RobotsTxt::class);
        $this->app->singleton(JsonLd::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(self::ROOT.'/database/migrations');
        $this->app->make(ActionRegistry::class)->register(InstallSeo::class, EnableSeo::class, DisableSeo::class, MapTemplate::class, UnmapTemplate::class);
        $permissions = $this->app->make(PermissionRegistry::class);
        $permissions->register('seo.manage', 'Manage SEO settings', requires: ['pages.view']);
        // The settings are the hidden /seo-settings/ page: managing SEO covers the page actions there.
        $permissions->delegate(SeoSchema::SETTINGS_TEMPLATE, 'seo.manage');
        $this->app->make(ReadToolRegistry::class)->register(InspectSeoTool::class);
        // Template mappings that would make broken structured data are refused, and the save rolled back.
        $this->app->make(HookRegistry::class)->after(SavePage::class, fn (mixed $page) => app(MappingValidator::class)->check($page));
        Lynx::macro('seo', fn (Page $page, array $overrides = []): SeoData => app(SeoResolver::class)->resolve($page, array_map(Value::string(...), Value::map($overrides))));

        $this->loadTranslationsFrom(self::ROOT.'/lang', 'lynx-seo');
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-seo');
        Blade::component('lynx-seo', SeoComponent::class);
        Blade::directive('seo', fn (string $page): string => '<?php echo app('.SeoHead::class.'::class)->html('.$page.'); ?>');

        // Registered before Lynx's catch-all page route, which is added once the app has booted.
        if ((bool) config('lynx-seo.robots.enabled', true)) {
            Route::get('robots.txt', RobotsController::class)->name('lynx.robots');
        }

        if ($this->app->bound(Navigation::class)) {
            AdminIntegration::register();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([self::ROOT.'/config/lynx-seo.php' => config_path('lynx-seo.php')], 'lynx-seo-config');
            $this->publishes([self::ROOT.'/resources/views' => resource_path('views/vendor/lynx-seo')], 'lynx-seo-views');
        }
    }
}
