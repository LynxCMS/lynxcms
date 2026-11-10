<?php

declare(strict_types=1);

namespace LynxCms\Seo\Admin;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminBranches;
use LynxCms\Admin\AdminSlots;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Editor\TemplateChoices;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Pages\Page;
use LynxCms\Seo\Admin\Http\ShowSeoPreview;
use LynxCms\Seo\Admin\Livewire\SeoAudit;
use LynxCms\Seo\Admin\Livewire\SeoSchemas;
use LynxCms\Seo\Admin\Livewire\SeoTemplates;
use LynxCms\Seo\SeoSchema;

/**
 * Adds the SEO screens to the admin: templates with SEO on or off, structured data per template,
 * an audit of titles and descriptions, and a preview of any page as a search result and a social card. The settings
 * page is edited in the page editor. Called only when lynxcms/admin is installed.
 *
 * @internal
 */
final class AdminIntegration
{
    private const int NAVIGATION_ORDER = 30;

    public static function register(): void
    {
        Livewire::component('lynx-seo.templates', SeoTemplates::class);
        Livewire::component('lynx-seo.audit', SeoAudit::class);
        Livewire::component('lynx-seo.schemas', SeoSchemas::class);
        $canManage = fn (): bool => app(AdminAccess::class)->allows('seo.manage');
        app(Navigation::class)->add('lynx-seo::admin.nav', 'lynx.admin.seo', order: self::NAVIGATION_ORDER, visible: $canManage, icon: 'search-check', group: Navigation::SETTINGS);

        Admin::routes(function (): void {
            Admin::screen('seo', 'lynx-seo::admin.index', 'seo.manage')->name('seo');
            Admin::screen('seo/audit', 'lynx-seo::admin.audit', 'seo.manage')->name('seo.audit');
            Admin::screen('seo/structured-data', 'lynx-seo::admin.schemas', 'seo.manage')->name('seo.schemas');
            Route::get('seo/preview/{page}', ShowSeoPreview::class)->whereNumber('page')->name('seo.preview');
        });

        // The settings page stays in the page editor, but out of the page tree.
        app(AdminBranches::class)->register(SeoSchema::SETTINGS_TEMPLATE, fn (): ?string => null);
        app(TemplateChoices::class)->exclude(SeoSchema::SETTINGS_TEMPLATE);
        app(SchemaOwners::class)->register('SEO', [SeoSchema::SETTINGS_TEMPLATE], SeoSchema::names());
        // The preview of the saved page, below the SEO tab's fields.
        app(AdminSlots::class)->addView(AdminSlots::editorTab(SeoSchema::TAB), 'lynx-seo::admin.partials.editor-preview',
            fn (Page $page): bool => $page->templateField(SeoSchema::TITLE) !== null && $page->template()->hasUrl());
    }
}
