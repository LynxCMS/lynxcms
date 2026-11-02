<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

/**
 * How tinker shows a page (through tinker.casters): what __debugInfo() gives,
 * instead of every property. VarDumper adds __debugInfo() to the properties rather than using it
 * in their place, so without this the loader and both registries the page holds would still show.
 *
 * @internal
 */
final class PageCaster
{
    /** @return array<string, mixed> */
    public static function cast(Page $page): array
    {
        return $page->__debugInfo();
    }
}
