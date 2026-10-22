<?php

declare(strict_types=1);

namespace LynxCms\Menus\Reading;

use Illuminate\Database\Eloquent\Builder;
use LynxCms\Core\Events\ContentChanged;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\Models\MenuBuild;
use LynxCms\Menus\Models\MenuBuildPage;

/**
 * Stored menus (ADR-0068): each menu built per language for the default visitor, read back with one
 * query. A build records the pages it used and the pages whose children it lists; a change to any
 * of them, or a change that may touch every page, drops it, and the next default visitor builds it
 * again.
 *
 * @internal
 */
final class MenuBuilds
{
    public const string BUILDS = MenuBuild::TABLE;

    public const string PAGES = MenuBuildPage::TABLE;

    public function find(string $name, int $languageId): ?Menu
    {
        $data = MenuBuild::query()->where('menu_name', $name)->where('language_id', $languageId)->value('data');

        return is_string($data) ? StoredMenu::decode($data) : null;
    }

    /**
     * @param  list<int>  $pages  pages the build shows or depends on
     * @param  list<int>  $childrenOf  pages whose children the build lists
     */
    public function store(Menu $menu, int $languageId, array $pages, array $childrenOf): void
    {
        // Dependencies first: a change between the two writes drops a build that has none yet stored.
        MenuBuildPage::query()->insertOrIgnore([
            ...array_map(fn (int $id): array => ['menu_id' => $menu->id, 'page_id' => $id, 'kind' => MenuBuildPage::PAGE], array_values(array_unique($pages))),
            ...array_map(fn (int $id): array => ['menu_id' => $menu->id, 'page_id' => $id, 'kind' => MenuBuildPage::CHILDREN], array_values(array_unique($childrenOf))),
        ]);
        MenuBuild::query()->upsert(
            [['menu_id' => $menu->id, 'menu_name' => $menu->name, 'language_id' => $languageId, 'data' => StoredMenu::encode($menu), 'built_at' => now()]],
            ['menu_name', 'language_id'],
            ['menu_id', 'data', 'built_at'],
        );
    }

    /**
     * Drops the builds of these menus, or of every menu. Returns how many builds there were.
     *
     * @param  list<int>|null  $menuIds
     */
    public function forget(?array $menuIds = null): int
    {
        $scope = fn (Builder $query): Builder => $menuIds === null ? $query : $query->whereIn('menu_id', $menuIds);
        $count = $scope(MenuBuild::query())->count();
        $scope(MenuBuild::query())->delete();
        $scope(MenuBuildPage::query())->delete();

        return $count;
    }

    /** How many builds a menu, or every menu, has. */
    public function count(?int $menuId = null): int
    {
        return MenuBuild::query()->when($menuId !== null, fn (Builder $query) => $query->where('menu_id', $menuId))->count();
    }

    public function handle(ContentChanged $event): void
    {
        $pages = $event->affectedPages();

        if ($pages === null) {
            $this->forget();
        } elseif ($pages !== [] && MenuBuild::query()->exists()) {
            $this->forget($this->menusUsing($pages));
        }
    }

    /**
     * Menus whose build used one of these pages, or lists the children of one of their parents.
     *
     * @param  list<int>  $pages
     * @return list<int>
     */
    private function menusUsing(array $pages): array
    {
        $parents = array_values(array_filter(array_map(
            fn (Page $page): ?int => $page->parentId,
            app(PageLoader::class)->many($pages)->all(),
        )));

        return Value::ints(MenuBuildPage::query()
            ->where(fn (Builder $query) => $query->where('kind', MenuBuildPage::PAGE)->whereIn('page_id', $pages))
            ->orWhere(fn (Builder $query) => $query->where('kind', MenuBuildPage::CHILDREN)->whereIn('page_id', $parents))
            ->distinct()->pluck('menu_id')->all());
    }
}
