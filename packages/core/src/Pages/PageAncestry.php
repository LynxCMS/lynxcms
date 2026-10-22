<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * A page's ancestors from the closure table, the page itself included, cached per request (for
 * branch scoped permission grants, ADR-0033). A page in the trash also counts the ancestors of
 * the place it was trashed from, so grants limited to a branch still reach it there (ADR-0035).
 *
 * @internal
 */
final class PageAncestry implements Flushable
{
    /** @var array<int, list<int>> */
    private array $cache = [];

    public function __construct(private readonly Trash $trash) {}

    /**
     * @return list<int> the page and every page above it
     */
    public function idsOf(int $pageId): array
    {
        if (isset($this->cache[$pageId])) {
            return $this->cache[$pageId];
        }

        $ids = Value::ints(DB::table(Tables::PAGE_CLOSURE)->where('descendant_id', $pageId)->pluck('ancestor_id')->all());

        return $this->cache[$pageId] = array_values(array_unique([...$ids, ...$this->trash->originalAncestorsAmong($ids)]));
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
