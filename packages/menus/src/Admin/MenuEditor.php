<?php

declare(strict_types=1);

namespace LynxCms\Menus\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\PageSearch;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\Actions\RebuildMenus;
use LynxCms\Menus\Admin\Forms\ItemForm;
use LynxCms\Menus\Admin\Forms\MenuForm;
use LynxCms\Menus\Admin\Forms\SettingsForm;
use LynxCms\Menus\MenuSchema;

/**
 * The menus screen: a list of menus, a tree of the chosen menu's items (the admin page tree
 * rooted at the menu) and forms for items and menu settings. Writes go through the menu and
 * pages actions; their validation errors show next to the fields.
 *
 * @internal
 */
final class MenuEditor extends Component
{
    private const int SEARCH_MIN_LENGTH = 2;

    private const int SEARCH_LIMIT = 8;

    #[Url(as: 'menu')]
    public ?int $menuId = null;

    public MenuForm $newMenu;

    public ItemForm $item;

    public SettingsForm $settings;

    public bool $isEditingItem = false;

    public string $pageSearch = '';

    #[Locked]
    public bool $confirmingDelete = false;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('menus.manage'), 403, 'You may not manage menus.');
    }

    /** An item to open, from the URL (the page editor sends menu items here). */
    #[Url(as: 'item')]
    public ?int $openItem = null;

    public function mount(?int $menuId = null): void
    {
        $this->select($menuId ?? $this->menuId);

        if ($this->openItem !== null) {
            $this->editItem($this->openItem);
            $this->openItem = null;
        }
    }

    public function selectMenu(int $id): void
    {
        $this->select($id);
    }

    public function createMenu(): void
    {
        $menu = lynx()->menus()->create($this->newMenu->name, $this->newMenu->title);

        if ($menu instanceof Page) {
            $this->newMenu->reset();
            $this->select((int) $menu->id);
        }
    }

    #[On('menu-item-new')]
    public function newItem(int $parent): void
    {
        $this->item->startNew($parent);
        $this->pageSearch = '';
        $this->isEditingItem = true;
    }

    #[On('menu-item-edit')]
    public function editItem(int $id): void
    {
        $page = lynx()->pages->get($id);

        if ($page instanceof Page && $page->template()->name === MenuSchema::ITEM_TEMPLATE) {
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
        }
    }

    /** Drops the menu's stored copies (menus.rebuild), for changes made outside Lynx. */
    public function rebuild(): void
    {
        $menu = $this->menuPage();

        if ($menu instanceof Page) {
            lynx()->run(RebuildMenus::class, ['menu' => $menu->name(lynx()->languages->default())]);
            $this->dispatch('lynx-notify', message: 'Menu rebuilt: the next visit builds it again.', type: 'success');
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

        $this->select(null);
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    /**
     * @return list<array{id: int, name: string, title: string}>
     */
    #[Computed]
    public function menus(): array
    {
        return array_values(lynx()->menus()->all()->map(fn (Page $menu): array => [
            'id' => (int) $menu->id,
            'name' => $menu->name(lynx()->languages->default()),
            'title' => Value::string($menu->get('title')),
        ]));
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    #[Computed]
    public function languages(): array
    {
        return array_values(lynx()->languages->all()->map(fn ($language): array => ['code' => Value::string($language->code), 'name' => Value::string($language->name), 'label' => $language->tabLabel()])->all());
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

        return view('lynx-menus::admin.editor', ['menuPage' => $menu, 'menuTitle' => Value::string($menu?->get('title'))]);
    }

    private function select(?int $id): void
    {
        $menus = $this->menus();
        $ids = array_column($menus, 'id');
        $this->menuId = in_array($id, $ids, true) ? $id : ($ids[0] ?? null);
        $this->isEditingItem = false;
        $this->confirmingDelete = false;
        unset($this->menus);

        $menu = $this->menuPage();

        if ($menu instanceof Page) {
            $this->settings->fillFrom($menu);
        }
    }

    private function menuPage(): ?Page
    {
        $page = $this->menuId !== null ? lynx()->pages->get($this->menuId) : null;

        return $page instanceof Page && $page->template()->name === MenuSchema::MENU_TEMPLATE ? $page : null;
    }

    private function menuName(): string
    {
        return $this->menuPage()?->name(lynx()->languages->default()) ?? '';
    }
}
