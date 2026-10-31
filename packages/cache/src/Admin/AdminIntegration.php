<?php

declare(strict_types=1);

namespace LynxCms\Cache\Admin;

use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminBranches;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Editor\TemplateChoices;
use LynxCms\Admin\EditorLinks;
use LynxCms\Admin\EditorTabs;
use LynxCms\Admin\Navigation;
use LynxCms\Cache\Admin\Livewire\FragmentsList;
use LynxCms\Cache\Admin\Livewire\PageCacheScreen;
use LynxCms\Cache\Admin\Livewire\PageCacheTab;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Cache\PageCacheSchema;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Adds the page cache screen to the admin: template rules, stored pages, clearing and the web
 * server rules, plus a Cache tab in the page editor and a link from it for pages that are stored. Called only when
 * lynxcms/admin is installed.
 *
 * @internal
 */
final class AdminIntegration
{
    private const int NAVIGATION_ORDER = 50;

    public static function register(): void
    {
        Livewire::component('lynx-cache.page-cache', PageCacheScreen::class);
        Livewire::component('lynx-cache.fragments', FragmentsList::class);
        Livewire::component('lynx-cache.page-tab', PageCacheTab::class);
        $canManage = fn (): bool => app(AdminAccess::class)->allows('page_cache.manage');
        app(Navigation::class)->add('lynx-cache::admin.title', 'lynx.admin.page-cache', order: self::NAVIGATION_ORDER, visible: $canManage, icon: 'zap', group: Navigation::SETTINGS);
        Admin::routes(fn () => Admin::screen('page-cache', 'lynx-cache::admin.index', 'page_cache.manage')->name('page-cache'));
        // The settings page is edited on the page cache screen, never as a page.
        app(AdminBranches::class)->register(PageCacheSchema::SETTINGS_TEMPLATE, fn (): string => route('lynx.admin.page-cache'));
        app(TemplateChoices::class)->exclude(PageCacheSchema::SETTINGS_TEMPLATE);
        app(SchemaOwners::class)->register('Page cache', [PageCacheSchema::SETTINGS_TEMPLATE], array_keys(PageCacheSchema::fields()));
        app(EditorTabs::class)->add('cache', 'lynx-cache::admin.tab', 'lynx-cache.page-tab', fn (Page $page): bool => $page->template()->hasUrl());
        app(EditorLinks::class)->add(fn (Page $page): ?array => $canManage() && PageCacheEntry::query()->where('page_id', $page->id)->exists()
            ? ['label' => Value::string(__('lynx-cache::admin.in_cache')), 'url' => route('lynx.admin.page-cache', ['page' => $page->id])]
            : null);
    }
}
