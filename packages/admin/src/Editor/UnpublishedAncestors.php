<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * For each language, the nearest parent of a page that is not published in it. Below such a parent
 * the page has no URL of its own in that language: its URL is built from the parent's URL in the
 * default language, which the Settings tab explains beside the URL. The home page is left out: its
 * URL is the language's prefix, which children keep.
 *
 * @internal
 */
final class UnpublishedAncestors
{
    /** @return array<string, string> language code => the parent's title */
    public static function of(Page $page): array
    {
        $found = [];

        foreach (lynx()->languages->all() as $language) {
            $code = Value::string($language->code);
            $ancestor = self::nearestUnpublished($page, $code);

            if ($ancestor instanceof Page) {
                $found[$code] = Value::string($ancestor->title);
            }
        }

        return $found;
    }

    private static function nearestUnpublished(Page $page, string $code): ?Page
    {
        for ($parent = $page->parent(); $parent instanceof Page && $parent->parentId !== null; $parent = $parent->parent()) {
            if (! $parent->isActiveIn($code)) {
                return $parent;
            }
        }

        return null;
    }
}
