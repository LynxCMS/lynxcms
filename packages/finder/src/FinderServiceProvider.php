<?php

declare(strict_types=1);

namespace LynxCms\Finder;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Home\HomeScreen;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Permissions\PermissionRegistry;
use LynxCms\Finder\Actions\DeleteSearch;
use LynxCms\Finder\Actions\SaveSearch;
use LynxCms\Finder\Admin\ExportResults;
use LynxCms\Finder\Admin\FinderScreen;

/**
 * The finder module: the admin's Find pages screen, where editors search every page with
 * conditions grouped by all and any, see the selector the search makes, save searches (ADR-0111),
 * change the pages found together and export them.
 */
final class FinderServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    /** Right after Pages in the sidebar. */
    private const int NAVIGATION_ORDER = 15;

    /** After the home screen's new page button. */
    private const int HOME_ORDER = 15;

    public function boot(): void
    {
        $this->app->make(ActionRegistry::class)->register(SaveSearch::class, DeleteSearch::class);
        $this->app->make(PermissionRegistry::class)->register('finder.share', 'Share saved searches in Find pages', requires: ['pages.view']);
        $this->loadMigrationsFrom(self::ROOT.'/database/migrations');
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-finder');
        $this->loadTranslationsFrom(self::ROOT.'/lang', 'lynx-finder');

        if (! $this->app->bound(Navigation::class)) {
            return;
        }

        $canView = fn (): bool => app(AdminAccess::class)->allows('pages.view');

        Livewire::component('lynx-finder.screen', FinderScreen::class);
        $this->app->make(Navigation::class)->add('lynx-finder::admin.nav', 'lynx.admin.finder', order: self::NAVIGATION_ORDER, visible: $canView, icon: 'search', shortcut: 'q');
        $this->app->make(HomeScreen::class)->action('finder', 'lynx-finder::admin.home_action', fn (): string => route('lynx.admin.finder'), icon: 'search', visible: $canView, order: self::HOME_ORDER);
        Admin::routes(function (): void {
            Admin::screen('finder', 'lynx-finder::admin.index', 'pages.view')->name('finder');
            Route::get('finder/export', ExportResults::class)->name('finder.export');
        });
    }
}
