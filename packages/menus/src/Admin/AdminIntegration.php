<?php

declare(strict_types=1);

namespace LynxCms\Menus\Admin;

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminBranches;
use LynxCms\Admin\Editor\BacklinkFilter;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Editor\TemplateChoices;
use LynxCms\Admin\Home\HomeScreen;
use LynxCms\Admin\Navigation;
use LynxCms\Core\Pages\Page;
use LynxCms\Menus\MenuSchema;

/**
 * Adds the menu editor to the admin. Called only when lynxcms/admin is installed.
 *
 * @internal
 */
final class AdminIntegration
{
    private const int NAVIGATION_ORDER = 20;

    public static function register(): void
    {
        Livewire::component('lynx-menus.list', MenuList::class);
        Livewire::component('lynx-menus.editor', MenuEditor::class);
        $canManage = fn (): bool => app(AdminAccess::class)->allows('menus.manage');
        app(Navigation::class)->add('lynx-menus::admin.title', 'lynx.admin.menus', order: self::NAVIGATION_ORDER, visible: $canManage, icon: 'menu', shortcut: 'm');
        app(HomeScreen::class)->action('menus', 'lynx-menus::admin.home_action', fn (): string => route('lynx.admin.menus'), icon: 'menu', visible: $canManage, order: 30);
        // Menus and items are created in the menu editor, never as plain pages.
        app(TemplateChoices::class)->exclude(MenuSchema::ROOT_TEMPLATE, MenuSchema::MENU_TEMPLATE, MenuSchema::ITEM_TEMPLATE);
        // A page's menu items are not content linking to it; the menu editor shows them.
        app(BacklinkFilter::class)->exclude(MenuSchema::ITEM_TEMPLATE);
        // Menus depend on these; the Templates and Fields screens show them read only.
        app(SchemaOwners::class)->register('Menus', [MenuSchema::ROOT_TEMPLATE, MenuSchema::MENU_TEMPLATE, MenuSchema::ITEM_TEMPLATE], array_keys(MenuSchema::fields()));
        Admin::routes(function (): void {
            Route::view('menus', 'lynx-menus::admin.index')->name('menus');
            Route::view('menus/{menu}', 'lynx-menus::admin.edit')->whereNumber('menu')->name('menus.edit');
        });
        // Menus live under /_menus/: out of the page tree, and edited here rather than as pages.
        app(AdminBranches::class)->register(MenuSchema::ROOT_TEMPLATE, self::editUrl(...));
    }

    /** The menu editor, on the page's menu and, for an item, with the item open. */
    private static function editUrl(Page $page, Page $root): string
    {
        if ($page->id === $root->id) {
            return route('lynx.admin.menus');
        }

        $menu = $page->template()->name === MenuSchema::MENU_TEMPLATE ? $page : lynx()->menus()->menuOf($page);

        return route('lynx.admin.menus.edit', array_filter(['menu' => $menu->id, 'item' => $page->id === $menu->id ? null : $page->id]));
    }
}
