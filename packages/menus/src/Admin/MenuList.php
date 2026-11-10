<?php

declare(strict_types=1);

namespace LynxCms\Menus\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\Admin\Forms\MenuForm;

/**
 * The Menus screen: every menu with its name and how many items it has at the top level, each
 * opening its editor (MenuEditor), and the form for a new menu in a dialog.
 *
 * @internal
 */
final class MenuList extends Component
{
    public MenuForm $newMenu;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('menus.manage'), 403, Value::string(__('lynx-menus::admin.denied')));
    }

    /** Links from before menus had a screen each (menus?menu=3&item=9) open that menu's screen. */
    public function mount(): void
    {
        $menu = request()->query('menu');

        if (is_string($menu) && ctype_digit($menu)) {
            $item = request()->query('item');
            $this->redirectRoute('lynx.admin.menus.edit', array_filter(['menu' => (int) $menu, 'item' => is_string($item) && ctype_digit($item) ? (int) $item : null]));
        }
    }

    /** Creates the menu (menus.create) and opens its editor; errors show in the dialog. */
    public function createMenu(): void
    {
        $menu = lynx()->menus()->create($this->newMenu->name, $this->newMenu->title);

        if ($menu instanceof Page) {
            $this->newMenu->reset();
            $this->redirectRoute('lynx.admin.menus.edit', ['menu' => $menu->id], navigate: true);
        }
    }

    public function render(): View
    {
        $menus = lynx()->menus()->all();
        $counts = lynx()->pages->childCounts($menus->ids(), 'include=all');

        return view('lynx-menus::admin.list', [
            'menus' => array_values($menus->map(fn (Page $menu): array => [
                'id' => (int) $menu->id,
                'name' => $menu->name(lynx()->languages->default()),
                'title' => Value::string($menu->get('title')),
                'items' => $counts[(int) $menu->id] ?? 0,
            ])),
            'languages' => MenuLanguages::all(),
        ]);
    }
}
