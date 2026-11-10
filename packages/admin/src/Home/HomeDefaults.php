<?php

declare(strict_types=1);

namespace LynxCms\Admin\Home;

use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Tree\TreeFilters;
use LynxCms\Core\Pages\Page;

/**
 * Lynx's own stats and actions on the home screen (HomeScreen): how many pages there are and how
 * many are not published, the languages, and buttons for a new page and the site texts.
 *
 * @internal
 */
final class HomeDefaults
{
    public static function register(HomeScreen $home): void
    {
        $filters = fn (): TreeFilters => new TreeFilters(app(AdminAccess::class)->viewScope());
        $canManageSchema = fn (): bool => app(AdminAccess::class)->allows('schema.manage');

        $home->stat('pages', 'lynx-admin::home.stats.pages', fn (): int => $filters()->total(), url: fn (): string => route('lynx.admin.pages'), icon: 'files', order: 10);
        $home->stat('unpublished', 'lynx-admin::home.stats.unpublished', fn (): int => $filters()->total('status=unpublished'), icon: 'eye-off', order: 20);
        $home->stat('languages', 'lynx-admin::home.stats.languages', fn (): ?int => lynx()->languages->isMultilingual() ? lynx()->languages->all()->count() : null,
            url: fn (): ?string => $canManageSchema() ? route('lynx.admin.languages') : null, icon: 'languages', order: 30);

        $home->action('new-page', 'lynx-admin::home.actions.new_page', fn (): string => route('lynx.admin.pages.create', ['parent' => self::homePage()?->id]),
            icon: 'plus', visible: fn (): bool => self::mayAddUnderHome(), order: 10);
        $home->action('site-texts', 'lynx-admin::home.actions.site_texts', fn (): string => route('lynx.admin.site-texts'),
            icon: 'type', visible: fn (): bool => app(AdminAccess::class)->allows('pages.edit'), order: 20);
    }

    /** New pages start under the home page; a site whose home page takes no children starts them from the tree. */
    private static function mayAddUnderHome(): bool
    {
        $home = self::homePage();

        return $home instanceof Page && $home->template()->allowsChildren() && app(AdminAccess::class)->allows('pages.create', $home);
    }

    private static function homePage(): ?Page
    {
        return lynx()->pages->get('/');
    }
}
