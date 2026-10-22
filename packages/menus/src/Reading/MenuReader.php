<?php

declare(strict_types=1);

namespace LynxCms\Menus\Reading;

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\MenuSchema;
use LynxCms\Menus\Support\LinkRules;

/**
 * Resolves a menu's structure into Menu and MenuItem objects for the current language and visitor:
 * labels with fallback, page URLs, automatic child pages, link safety and the active trail.
 *
 * @internal
 */
final readonly class MenuReader
{
    /** The menu page and its items, from the database: what MenuCache keeps. */
    public function structure(Page $menu): MenuStructure
    {
        $items = lynx()->pages->find("has_parent={$menu->id}, template=".MenuSchema::ITEM_TEMPLATE.', include=all');

        return new MenuStructure($menu, $this->list($items));
    }

    public function read(MenuStructure $structure): Menu
    {
        $menu = $structure->menu;
        $byParent = [];

        foreach ($structure->items as $item) {
            $byParent[(int) $item->parentId][] = $item;
        }

        $language = lynx()->languages->current();
        $context = new ReadContext(
            $byParent,
            $this->visiblePages($structure->items),
            $language,
            $language->id === lynx()->languages->default()->id,
        );

        return new Menu(
            (int) $menu->id,
            $menu->name(lynx()->languages->default()),
            $this->text($menu->get('title')),
            $this->optionalText($menu->get(MenuSchema::CSS_CLASS)),
            $this->items((int) $menu->id, 0, $context),
        );
    }

    /**
     * @return list<MenuItem>
     */
    private function items(int $parentId, int $depth, ReadContext $context): array
    {
        $items = [];

        foreach ($context->byParent[$parentId] ?? [] as $page) {
            $item = $context->isShown($page) ? $this->item($page, $depth, $context) : null;

            if ($item instanceof MenuItem) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function item(Page $item, int $depth, ReadContext $context): ?MenuItem
    {
        $target = $this->linkedPage($item, $context);
        $label = $this->text($item->get('title'));
        $children = $this->items((int) $item->id, $depth + 1, $context);

        if ($target instanceof Page) {
            $label = $label !== '' ? $label : $this->pageLabel($target);
            $auto = $item->get(MenuSchema::CHILDREN) === true ? $this->pageChildren($target, $depth + 1, 1, ChildPages::of($item), $context) : [];

            return $this->make((int) $item->id, 'page', $label, $target->url(), $item, $depth, [...$children, ...$auto], (int) $target->id);
        }

        if (Value::ints((array) $item->getUnformatted(MenuSchema::PAGE)) !== []) {
            return null; // The linked page is gone, unpublished or not visible to this visitor (ADR-0062): leave the item out.
        }

        $url = $this->optionalText($item->get(MenuSchema::URL));
        $url = $url !== null && LinkRules::isSafeUrl($url) ? $url : null;

        return $this->make((int) $item->id, $url === null ? 'section' : 'link', $label, $url, $item, $depth, $children);
    }

    /**
     * Child pages of a page item, down to the item's depth and up to its limit per level.
     *
     * @return list<MenuItem>
     */
    private function pageChildren(Page $page, int $depth, int $level, ChildPages $options, ReadContext $context): array
    {
        if ($level > $options->depth) {
            return [];
        }

        $items = [];

        foreach ($this->list($page->children("limit={$options->limit}")) as $child) {
            if (! $context->isAvailable($child)) {
                continue;
            }

            $children = $this->pageChildren($child, $depth + 1, $level + 1, $options, $context);
            $items[] = new MenuItem(null, 'page', $this->pageLabel($child), $child->url(), null, null, null, $depth, false, false, $children, (int) $child->id);
        }

        return $items;
    }

    /**
     * @param  'section'|'page'|'link'  $kind
     * @param  list<MenuItem>  $children
     */
    private function make(int $id, string $kind, string $label, ?string $url, Page $item, int $depth, array $children, ?int $pageId = null): MenuItem
    {
        $target = $kind === 'section' ? null : $this->optionalText($item->get(MenuSchema::TARGET));

        return new MenuItem(
            $id,
            $kind,
            $label,
            $url,
            $target,
            LinkRules::relFor($target, $kind === 'section' || $item->templateField(MenuSchema::REL) === null ? '' : (string) $this->optionalText($item->get(MenuSchema::REL))),
            $this->optionalText($item->get(MenuSchema::CSS_CLASS)),
            $depth,
            false,
            false,
            $children,
            $pageId,
            $this->optionalText($item->get(MenuSchema::ICON_BEFORE)),
            $this->optionalText($item->get(MenuSchema::ICON_AFTER)),
        );
    }

    private function linkedPage(Page $item, ReadContext $context): ?Page
    {
        $page = $item->get(MenuSchema::PAGE);

        return $page instanceof Page && isset($context->visiblePages[(int) $page->id]) && $page->template()->hasUrl() && $context->isAvailable($page)
            ? $page
            : null;
    }

    /**
     * Linked pages a visitor may see, in one query: the default selector rules leave out hidden,
     * unpublished and system pages, and pages below an unpublished page (ADR-0026). Every linked
     * page is loaded first in one batch, so reading an item's page later costs no query.
     *
     * @param  list<Page>  $items
     * @return array<int, true>
     */
    private function visiblePages(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $ids = [...$ids, ...Value::ints((array) $item->getUnformatted(MenuSchema::PAGE))];
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        app(PageLoader::class)->many($ids);

        return array_fill_keys(lynx()->pages->find('id='.implode('|', $ids))->ids(), true);
    }

    private function pageLabel(Page $page): string
    {
        $title = $this->text($page->get('title'));

        return $title !== '' ? $title : $page->name();
    }

    /**
     * @return list<Page>
     */
    private function list(PageArray $pages): array
    {
        return array_values($pages->map(fn (Page $page): Page => $page));
    }

    private function text(mixed $value): string
    {
        return trim(Value::string($value));
    }

    private function optionalText(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === '' ? null : $text;
    }
}
