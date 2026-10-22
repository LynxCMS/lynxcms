<?php

declare(strict_types=1);

namespace LynxCms\Seo\Admin;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminBranches;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Editor\TemplateChoices;
use LynxCms\Admin\EditorLinks;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Pages\Page;
use LynxCms\Seo\Admin\Livewire\SeoAudit;
use LynxCms\Seo\Admin\Livewire\SeoSchemas;
use LynxCms\Seo\Admin\Livewire\SeoTemplates;
use LynxCms\Seo\SeoResolver;
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
        app(Navigation::class)->add('SEO', 'lynx.admin.seo', order: self::NAVIGATION_ORDER, visible: $canManage, icon: 'search-check', group: Navigation::SETTINGS);

        Admin::routes(function () use ($canManage): void {
            Route::get('seo', fn () => $canManage() ? view('lynx-seo::admin.index') : abort(403))->name('seo');
            Route::get('seo/audit', fn () => $canManage() ? view('lynx-seo::admin.audit') : abort(403))->name('seo.audit');
            Route::get('seo/structured-data', fn () => $canManage() ? view('lynx-seo::admin.schemas') : abort(403))->name('seo.schemas');
            Route::get('seo/preview/{page}', fn (int $page): mixed => self::preview($page))->whereNumber('page')->name('seo.preview');
        });

        // The settings page stays in the page editor, but out of the page tree.
        app(AdminBranches::class)->register(SeoSchema::SETTINGS_TEMPLATE, fn (): ?string => null);
        app(TemplateChoices::class)->exclude(SeoSchema::SETTINGS_TEMPLATE);
        app(SchemaOwners::class)->register('SEO', [SeoSchema::SETTINGS_TEMPLATE], SeoSchema::names());
        app(EditorLinks::class)->add(fn (Page $page): ?array => $page->templateField(SeoSchema::TITLE) !== null
            ? ['label' => 'SEO preview', 'url' => route('lynx.admin.seo.preview', $page->id)]
            : null);
    }

    /** Anyone who may see the page in the admin may preview it. */
    private static function preview(int $id): mixed
    {
        $page = lynx()->pages->get($id);
        abort_if($page === null || ! $page->template()->hasUrl() || ! app(AdminAccess::class)->allows('pages.view'), 404);

        return view('lynx-seo::admin.preview', ['page' => $page, 'seo' => app(SeoResolver::class)->resolve($page)]);
    }
}
