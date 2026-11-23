<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

/**
 * The warning a trash or delete confirmation shows when pages elsewhere reference the branches
 * about to go: how many, and the first few (LinkedPages rows), to open and fix first.
 *
 * @internal
 */
final class ReferenceWarning
{
    /** Pages named in the warning; the rest are counted. */
    public const int SHOWN = 3;

    /**
     * @param  list<int>  $tops  the top page of each branch
     * @return array{count: int, pages: list<array{id: int, title: string, context: string}>}|null null when nothing references them
     */
    public static function for(array $tops): ?array
    {
        $linking = lynx()->pages->backlinks($tops, limit: self::SHOWN, branch: true);

        return $linking->isEmpty() ? null : ['count' => $linking->total(), 'pages' => LinkedPages::rows($linking)];
    }
}
