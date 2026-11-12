<?php

declare(strict_types=1);

namespace LynxCms\Menus\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Editor\PageSearch;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\Actions\RebuildMenus;
use LynxCms\Menus\Admin\Forms\ItemForm;
use LynxCms\Menus\Admin\Forms\SettingsForm;
use LynxCms\Menus\MenuSchema;

/**
 * One menu's screen: its items as a tree (the admin page tree rooted at the menu) with the item
 * form in a panel beside it, and a Settings tab for its title, classes, stored copies and
 * deletion. Writes go through the menu and pages actions; their errors show next to the fields.
 *
 * @internal
 */
final class MenuEditor extends Component
{
    private const int SEARCH_MIN_LENGTH = 2;

    private const int SEARCH_LIMIT = 8;

    #[Locked]
    public int $menuId;

    /** "items" or "settings", kept in the URL. */
    #[Url]
    public string $tab = 'items';

    public ItemForm $item;

    public SettingsForm $settings;

    public bool $isEditingItem = false;

    public string $pageSearch = '';

    #[Locked]
    public bool $confirmingDelete = false;

    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('menus.manage', Value::string(__('lynx-menus::admin.denied')));
    }

    /** An item to open, from the URL (the page editor sends menu items here). */
    #[Url(as: 'item')]
    public ?int $openItem = null;

    public function mount(int $menuId): void
    {
        $this->menuId = $menuId;
        $this->settings->fillFrom($this->menuPage() ?? abort(404));

        if ($this->openItem !== null) {
            $this->editItem($this->openItem);
            $this->openItem = null;
        }
    }

    #[On('menu-item-new')]
    public function newItem(int $parent): void
    {
        $this->tab = 'items';
        $this->item->startNew($parent);
        $this->pageSearch = '';
        $this->isEditingItem = true;
    }

    #[On('menu-item-edit')]
    public function editItem(int $id): void
    {
        $page = lynx()->pages->get($id);

        if ($page instanceof Page && $page->template()->name === MenuSchema::ITEM_TEMPLATE) {
            $this->tab = 'items';
            $this->item->fillFrom($page);
            $this->pageSearch = '';
            $this->isEditingItem = true;
        }
    }

    public function choosePage(int $id): void
    {
        $page = lynx()->pages->get($id);

        if ($page instanceof Page) {
            $this->item->choosePage($page);
            $this->pageSearch = '';
        }
    }

    public function saveItem(): void
    {
        $saved = lynx()->menus()->saveItem($this->item->toInput($this->menuName()));

        if ($saved instanceof Page) {
            $this->dispatch('lynx-tree-refresh', expand: $saved->parentId);
            $this->isEditingItem = false;
        }
    }

    public function cancelItem(): void
    {
        $this->isEditingItem = false;
    }

    public function saveSettings(): void
    {
        $menu = $this->menuPage();

        if ($menu instanceof Page) {
            $this->settings->save($menu);
            $this->dispatch('lynx-tree-refresh');
            $this->dispatch('lynx-notify', message: __('lynx-menus::admin.settings.saved'), type: 'success');
        }
    }

    /** Drops the menu's stored copies (menus.rebuild), for changes made outside Lynx. */
    public function rebuild(): void
    {
        $menu = $this->menuPage();

        if ($menu instanceof Page) {
            lynx()->run(RebuildMenus::class, ['menu' => $menu->name(lynx()->languages->default())]);
            $this->dispatch('lynx-notify', message: __('lynx-menus::admin.rebuilt'), type: 'success');
        }
    }

    /** First call asks for confirmation, the second deletes the menu and its items. */
    public function deleteMenu(): void
    {
        if (! $this->confirmingDelete) {
            $this->confirmingDelete = true;

            return;
        }

        $menu = $this->menuPage();

        if ($menu instanceof Page) {
            lynx()->pages->delete((int) $menu->id);
        }

        $this->redirectRoute('lynx.admin.menus', navigate: true);
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    /**
     * @return list<array{code: string, name: string, label: string}>
     */
    #[Computed]
    public function languages(): array
    {
        return MenuLanguages::all();
    }

    /**
     * Pages with URLs whose title contains the search text.
     *
     * @return list<array{id: int, title: string, url: string}>
     */
    #[Computed]
    public function pageResults(): array
    {
        $search = trim($this->pageSearch);

        if (mb_strlen($search) < self::SEARCH_MIN_LENGTH) {
            return [];
        }

        $pages = app(PageSearch::class)->find($search, self::SEARCH_LIMIT);

        return array_values(array_filter($pages->map(fn (Page $page): ?array => $page->template()->hasUrl()
            ? ['id' => (int) $page->id, 'title' => Value::string($page->get('title')) ?: $page->name(), 'url' => $page->url()]
            : null)));
    }

    public function render(): View
    {
        $menu = $this->menuPage();

        return view('lynx-menus::admin.editor', [
            'menuPage' => $menu,
            'menuTitle' => Value::string($menu?->get('title')),
            'menuName' => $this->menuName(),
            // Written out here: Blade would compile the tag as a component inside a view.
            'menuTag' => sprintf('<x-lynx-menu name="%s" view="lynx-menus::menus.dropdown" />', $this->menuName()),
            'tab' => $this->tab === 'settings' ? 'settings' : 'items',
        ]);
    }

    private function menuPage(): ?Page
    {
        $page = lynx()->pages->get($this->menuId);

        return $page instanceof Page && $page->template()->name === MenuSchema::MENU_TEMPLATE ? $page : null;
    }

    private function menuName(): string
    {
        return $this->menuPage()?->name(lynx()->languages->default()) ?? '';
    }
}
