<?php

declare(strict_types=1);

namespace LynxCms\Finder;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Home\HomeScreen;
use LynxCms\Admin\Navigation;
use LynxCms\Finder\Admin\FinderScreen;

/**
 * The finder module: the admin's Find pages screen, where editors search every page with
 * conditions grouped by all and any, and see the selector the search makes.
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
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-finder');
        $this->loadTranslationsFrom(self::ROOT.'/lang', 'lynx-finder');

        if (! $this->app->bound(Navigation::class)) {
            return;
        }

        $canView = fn (): bool => app(AdminAccess::class)->allows('pages.view');

        Livewire::component('lynx-finder.screen', FinderScreen::class);
        $this->app->make(Navigation::class)->add('lynx-finder::admin.nav', 'lynx.admin.finder', order: self::NAVIGATION_ORDER, visible: $canView, icon: 'search', shortcut: 'q');
        $this->app->make(HomeScreen::class)->action('finder', 'lynx-finder::admin.home_action', fn (): string => route('lynx.admin.finder'), icon: 'search', visible: $canView, order: self::HOME_ORDER);
        Admin::routes(fn () => Admin::screen('finder', 'lynx-finder::admin.index', 'pages.view')->name('finder'));
    }
}
