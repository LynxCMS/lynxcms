<?php

declare(strict_types=1);

namespace LynxCms\Menus\Admin;

use LynxCms\Admin\Tree\TreePresenter;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\MenuSchema;
use LynxCms\Menus\Reading\ChildPages;

/**
 * Menu items in the page tree: their label (or the linked page's title) and what they point to.
 *
 * @internal
 */
final class MenuItemPresenter implements TreePresenter
{
    public function title(Page $page): string
    {
        $label = trim(Value::string($page->get('title')));
        $linked = $this->linkedPage($page);

        return match (true) {
            $label !== '' => $label,
            $linked instanceof Page => trim(Value::string($linked->get('title'))) ?: $linked->name(),
            default => '(no label)',
        };
    }

    public function detail(Page $page): string
    {
        if ($page->template()->name === MenuSchema::MENU_TEMPLATE) {
            return Value::string(__('lynx-menus::admin.tree.menu', ['name' => $page->name(lynx()->languages->default())]));
        }

        $linked = $this->linkedPage($page);
        $url = trim(Value::string($page->get(MenuSchema::URL)));
        $target = trim(Value::string($page->get(MenuSchema::TARGET)));
        $suffix = ($target !== '' ? " ({$target})" : '').$this->languagesNote($page);

        return match (true) {
            $linked instanceof Page => Value::string(__('lynx-menus::admin.tree.page', ['url' => $linked->url()])).$this->childPagesNote($page).$suffix,
            $url !== '' => Value::string(__('lynx-menus::admin.tree.link', ['url' => $url])).$suffix,
            default => Value::string(__('lynx-menus::admin.tree.section')).$this->languagesNote($page),
        };
    }

    /** " + children (2 levels, max 8)" when the item adds child pages. */
    private function childPagesNote(Page $page): string
    {
        if ($page->get(MenuSchema::CHILDREN) !== true) {
            return '';
        }

        $options = ChildPages::of($page);

        return Value::string(__('lynx-menus::admin.tree.children', ['depth' => trans_choice('lynx-menus::admin.tree.levels', $options->depth), 'limit' => $options->limit]));
    }

    /** " · only Português" for items limited to some languages. */
    private function languagesNote(Page $page): string
    {
        $codes = array_filter(explode(',', trim(Value::string($page->get(MenuSchema::LANGUAGES)))));

        if ($codes === []) {
            return '';
        }

        $names = array_map(fn (string $code): string => (string) lynx()->languages->find($code)?->name, $codes);

        return Value::string(__('lynx-menus::admin.tree.only', ['languages' => implode(', ', array_filter($names))]));
    }

    private function linkedPage(Page $page): ?Page
    {
        $linked = $page->get(MenuSchema::PAGE);

        return $linked instanceof Page ? $linked : null;
    }
}
