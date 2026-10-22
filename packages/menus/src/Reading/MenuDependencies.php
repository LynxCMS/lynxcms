<?php

declare(strict_types=1);

namespace LynxCms\Menus\Reading;

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\MenuSchema;

/**
 * The pages a built menu depends on (ADR-0068): the menu and its items, every linked page (shown
 * or not, since a hidden page can come back), the child pages it lists, and the pages above them,
 * whose status hides what is below; and the pages whose children it lists, the menu and its items
 * included, so a new item counts.
 *
 * @internal
 */
final readonly class MenuDependencies
{
    /**
     * @param  list<int>  $pages
     * @param  list<int>  $childrenOf
     */
    private function __construct(public array $pages, public array $childrenOf) {}

    public static function of(MenuStructure $structure, Menu $menu): self
    {
        $linked = [];
        $childrenOf = [];

        foreach ($structure->items as $item) {
            $targets = Value::ints((array) $item->getUnformatted(MenuSchema::PAGE));
            $linked = [...$linked, ...$targets];

            if ($item->getUnformatted(MenuSchema::CHILDREN) === true) {
                $childrenOf = [...$childrenOf, ...$targets];
            }
        }

        $listed = self::listedChildren($menu->items);
        $shown = [...$linked, ...$listed];
        $own = [(int) $structure->menu->id, ...array_map(fn (Page $item): int => (int) $item->id, $structure->items)];

        return new self(
            array_values(array_unique([...$own, ...$shown, ...self::ancestors($shown)])),
            array_values(array_unique([...$own, ...$childrenOf, ...$listed])),
        );
    }

    /**
     * Pages added as a page item's children (items without an id), at every level.
     *
     * @param  list<MenuItem>  $items
     * @return list<int>
     */
    private static function listedChildren(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            if ($item->id === null && $item->pageId !== null) {
                $ids[] = $item->pageId;
            }

            $ids = [...$ids, ...self::listedChildren($item->children)];
        }

        return $ids;
    }

    /**
     * Every page above these, loaded one level at a time.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function ancestors(array $ids): array
    {
        $loader = app(PageLoader::class);
        $found = [];
        $level = array_values(array_unique($ids));

        while ($level !== []) {
            $parents = array_values(array_unique(array_filter(array_map(fn (Page $page): ?int => $page->parentId, $loader->many($level)->all()))));
            $level = array_values(array_diff($parents, $found));
            $found = [...$found, ...$level];
        }

        return $found;
    }
}
