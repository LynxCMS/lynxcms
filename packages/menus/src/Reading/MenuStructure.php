<?php

declare(strict_types=1);

namespace LynxCms\Menus\Reading;

use LynxCms\Core\Pages\Page;

/**
 * A menu page and its item pages, in tree order: what MenuReader builds a menu from.
 *
 * @internal
 */
final readonly class MenuStructure
{
    /** @param list<Page> $items */
    public function __construct(public Page $menu, public array $items) {}
}
