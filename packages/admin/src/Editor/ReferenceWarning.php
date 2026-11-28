<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Pages\Page;

/**
 * The warning a trash or delete confirmation shows when pages elsewhere reference the branches
 * about to go: how many, and the first few (LinkedPages rows), to open and fix first. Only pages
 * the user may view are named; the others are counted.
 *
 * @internal
 */
final class ReferenceWarning
{
    /** Pages named in the warning; the rest are counted. */
    public const int SHOWN = 3;

    /** Referencing pages read to find the ones the user may view. */
    private const int CANDIDATES = 100;

    /**
     * @param  list<int>  $tops  the top page of each branch
     * @return array{count: int, pages: list<array{id: int, title: string, context: string}>}|null null when nothing references them
     */
    public static function for(array $tops): ?array
    {
        $linking = lynx()->pages->backlinks($tops, limit: self::CANDIDATES, branch: true);

        if ($linking->isEmpty()) {
            return null;
        }

        $viewable = app(AdminAccess::class)->viewable($linking->map(fn (Page $page): int => (int) $page->id));
        $shown = $linking->filter(fn (Page $page): bool => in_array((int) $page->id, $viewable, true));

        return ['count' => $linking->total(), 'pages' => array_slice(LinkedPages::rows($shown), 0, self::SHOWN)];
    }
}
