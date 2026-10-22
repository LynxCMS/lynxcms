<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use LynxCms\Core\Pages\Page;

/**
 * @internal
 */
final class Ancestors
{
    /** @return list<int> the ids above a page, nearest first */
    public static function idsOf(Page $page): array
    {
        $ids = [];

        for ($parent = $page->parent(); $parent !== null && $parent->id !== null; $parent = $parent->parent()) {
            $ids[] = $parent->id;
        }

        return $ids;
    }
}
