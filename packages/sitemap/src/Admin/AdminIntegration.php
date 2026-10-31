<?php

declare(strict_types=1);

namespace LynxCms\Sitemap\Admin;

use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminBranches;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Editor\TemplateChoices;
use LynxCms\Admin\Navigation;
use LynxCms\Sitemap\Admin\Livewire\SitemapSettings;
use LynxCms\Sitemap\SitemapSchema;

/**
 * Adds the sitemap screen to the admin: the limit per file, the grouping, the templates left out
 * and the parts. Called only when lynxcms/admin is installed.
 *
 * @internal
 */
final class AdminIntegration
{
    private const int NAVIGATION_ORDER = 40;

    public static function register(): void
    {
        Livewire::component('lynx-sitemap.settings', SitemapSettings::class);
        $canManage = fn (): bool => app(AdminAccess::class)->allows('sitemap.manage');
        app(Navigation::class)->add('lynx-sitemap::admin.title', 'lynx.admin.sitemap', order: self::NAVIGATION_ORDER, visible: $canManage, icon: 'map', group: Navigation::SETTINGS);
        Admin::routes(fn () => Admin::screen('sitemap', 'lynx-sitemap::admin.index', 'sitemap.manage')->name('sitemap'));

        // The settings page is edited on the sitemap screen, never as a page.
        app(AdminBranches::class)->register(SitemapSchema::SETTINGS_TEMPLATE, fn (): string => route('lynx.admin.sitemap'));
        app(TemplateChoices::class)->exclude(SitemapSchema::SETTINGS_TEMPLATE);
        app(SchemaOwners::class)->register('Sitemap', [SitemapSchema::SETTINGS_TEMPLATE], array_keys(SitemapSchema::fields()));
    }
}
