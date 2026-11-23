<?php

declare(strict_types=1);

namespace LynxCms\PageReferences;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Navigation;
use LynxCms\PageReferences\Admin\ReferencesScreen;

/**
 * The page references module (ADR-0145): an admin screen with reports on the links between pages
 * (broken links, pages nothing links to, and the most linked pages) and, for page_references.manage,
 * the index's health with a check and a queued rebuild. The index, its event and the rebuild action
 * live in core.
 *
 * @internal
 */
final class PageReferencesServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    /** Between the page cache and the static export in Settings. */
    private const int NAVIGATION_ORDER = 55;

    public function boot(): void
    {
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-page-references');
        $this->loadTranslationsFrom(self::ROOT.'/lang', 'lynx-page-references');

        if (! $this->app->bound(Navigation::class)) {
            return;
        }

        Livewire::component('lynx-page-references.screen', ReferencesScreen::class);
        $canView = fn (): bool => app(AdminAccess::class)->allows('pages.view');
        $this->app->make(Navigation::class)->add('lynx-page-references::admin.title', 'lynx.admin.page-references', order: self::NAVIGATION_ORDER, visible: $canView, icon: 'link', group: Navigation::SETTINGS);
        Admin::routes(fn () => Admin::screen('page-references', 'lynx-page-references::admin.index', 'pages.view')->name('page-references'));
    }
}
