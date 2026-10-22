<?php

declare(strict_types=1);

namespace LynxCms\Menus;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Fields\DeleteField;
use LynxCms\Core\Actions\Fields\UpdateField;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Actions\Templates\DeleteTemplate;
use LynxCms\Core\Events\ContentChanged;
use LynxCms\Core\Facades\Lynx as LynxFacade;
use LynxCms\Core\Lynx;
use LynxCms\Core\Permissions\PermissionRegistry;
use LynxCms\Menus\Actions\CreateMenu;
use LynxCms\Menus\Actions\InstallMenus;
use LynxCms\Menus\Actions\RebuildMenus;
use LynxCms\Menus\Actions\SaveMenuItem;
use LynxCms\Menus\Admin\AdminIntegration;
use LynxCms\Menus\Reading\MenuBuilds;
use LynxCms\Menus\View\IconRenderer;
use LynxCms\Menus\View\MenuComponent;

/**
 * Registers the menu actions (and so their MCP and AI tools), lynx()->menus(), the
 * <x-lynx-menu> component and its views.
 *
 * @api
 */
final class MenusServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    public function register(): void
    {
        $this->app->singleton(Menus::class);
        $this->app->singleton(IconRenderer::class);
    }

    public function boot(): void
    {
        $this->app->make(ActionRegistry::class)->register(InstallMenus::class, CreateMenu::class, SaveMenuItem::class, RebuildMenus::class);
        $this->app->make(PermissionRegistry::class)->register('menus.manage', 'Manage menus', requires: ['pages.view']);
        // Menus and items are pages under /_menus/: managing menus covers the page actions there.
        $this->app->make(PermissionRegistry::class)->delegate(MenuSchema::ROOT_TEMPLATE, 'menus.manage');
        Lynx::macro('menus', fn (): Menus => app(Menus::class));

        // Stored menus go when a page they use changes, and after schema changes that fire no
        // ContentChanged. Hooks run inside the action's transaction, so a rollback keeps them.
        $this->loadMigrationsFrom(self::ROOT.'/database/migrations');
        Event::listen(ContentChanged::class, [MenuBuilds::class, 'handle']);

        foreach ([CreateField::class, UpdateField::class, DeleteField::class, CreateTemplate::class, DeleteTemplate::class] as $action) {
            LynxFacade::hook($action)->after(fn (): int => app(MenuBuilds::class)->forget());
        }

        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-menus');
        Blade::component('lynx-menu', MenuComponent::class);

        // The editor needs the Livewire admin; without it menus still work from code, MCP and the agent.
        if ($this->app->bound(Navigation::class)) {
            AdminIntegration::register();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([self::ROOT.'/resources/views/menus' => resource_path('views/vendor/lynx-menus/menus')], 'lynx-menus-views');
        }
    }
}
