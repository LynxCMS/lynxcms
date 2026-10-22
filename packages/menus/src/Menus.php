<?php

declare(strict_types=1);

namespace LynxCms\Menus;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Menus\Actions\CreateMenu;
use LynxCms\Menus\Actions\SaveMenuItem;
use LynxCms\Menus\Reading\ActiveTrail;
use LynxCms\Menus\Reading\Menu;
use LynxCms\Menus\Reading\MenuBuilds;
use LynxCms\Menus\Reading\MenuDependencies;
use LynxCms\Menus\Reading\MenuReader;
use LynxCms\Menus\View\IconRenderer;
use UnexpectedValueException;

/**
 * lynx()->menus(): create menus and items, and read a menu resolved for the current language.
 *
 * @api
 */
final readonly class Menus
{
    public function __construct(private ActionRunner $runner, private MenuReader $reader, private IconRenderer $icons, private MenuBuilds $builds) {}

    /**
     * Render item icons your own way (SVG components, sprites). The default treats an icon as CSS classes.
     *
     * @param  Closure(string $icon, string $position): (Htmlable|string)  $renderer
     */
    public function renderIconsUsing(Closure $renderer): void
    {
        $this->icons->using($renderer);
    }

    /**
     * The menu, resolved for the current language, or null when it does not exist. Visitors who see
     * what everyone sees read the stored build (one query, ADR-0068), built on the first such read;
     * others get it built from the pages. The active link is marked per request.
     */
    public function get(string $name): ?Menu
    {
        $menu = $this->built($name);

        return $menu === null ? null : ActiveTrail::forRequest()->mark($menu);
    }

    private function built(string $name): ?Menu
    {
        $isShared = app(PageVisibility::class)->seesDefault();
        $language = lynx()->languages->current()->id;
        $stored = $isShared ? $this->builds->find($name, $language) : null;

        if ($stored instanceof Menu) {
            return $stored;
        }

        $page = $this->page($name);

        if (! $page instanceof Page) {
            return null;
        }

        $structure = $this->reader->structure($page);
        $menu = $this->reader->read($structure);

        if ($isShared) {
            $dependencies = MenuDependencies::of($structure, $menu);
            $this->builds->store($menu, $language, $dependencies->pages, $dependencies->childrenOf);
        }

        return $menu;
    }

    /**
     * @param  string|array<string, string>  $title  a title or one per language code
     */
    public function create(string $name, string|array $title, bool $dryRun = false): Page|ActionResult
    {
        return $this->runner->run(CreateMenu::class, ['name' => $name, 'title' => $title], $dryRun)
            ->valueOrDryRun(Page::class);
    }

    /**
     * Add or update an item; see menus.save_item for the input.
     *
     * @param  array<string, mixed>  $input
     */
    public function saveItem(array $input, bool $dryRun = false): Page|ActionResult
    {
        return $this->runner->run(SaveMenuItem::class, $input, $dryRun)->valueOrDryRun(Page::class);
    }

    /** The page holding every menu, or null before the menu builder is installed. */
    public function root(): ?Page
    {
        return $this->isInstalled() ? lynx()->pages->findOne('template='.MenuSchema::ROOT_TEMPLATE.', include=all') : null;
    }

    /** The page of a menu by name. */
    public function page(string $name): ?Page
    {
        $root = $this->root();

        if (! $root instanceof Page || preg_match(MenuSchema::NAME_PATTERN, $name) !== 1) {
            return null;
        }

        return lynx()->pages->findOne('template='.MenuSchema::MENU_TEMPLATE.", parent={$root->id}, name={$name}, language=".lynx()->languages->default()->code.', include=all');
    }

    /** Every menu page, in tree order. */
    public function all(): PageArray
    {
        $root = $this->root();

        return $root instanceof Page
            ? lynx()->pages->find('template='.MenuSchema::MENU_TEMPLATE.", parent={$root->id}, include=all")
            : new PageArray([]);
    }

    /** The menu page an item belongs to. */
    public function menuOf(Page $item): Page
    {
        $page = $item;

        while ($page instanceof Page && $page->template()->name !== MenuSchema::MENU_TEMPLATE) {
            $page = $page->parent();
        }

        return $page ?? throw new UnexpectedValueException("Page [{$item->id}] is not inside a menu.");
    }

    private function isInstalled(): bool
    {
        return lynx()->templates->find(MenuSchema::ROOT_TEMPLATE) !== null;
    }
}
