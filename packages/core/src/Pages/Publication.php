<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Whether visitors may see a page: it and every page above it must be published (ADR-0026).
 * Hidden pages stay reachable by URL; hidden only keeps them out of listings and search.
 *
 * An unpublished page above another always has children, so it is one of UnpublishedBranches,
 * loaded once per request. While that list is empty, as it usually is, no check needs a query.
 *
 * @api
 */
final readonly class Publication
{
    public function __construct(private UnpublishedBranches $branches) {}

    public function isPublished(Page $page): bool
    {
        return $this->published([$page]) !== [];
    }

    /**
     * The pages visitors may see, in their order, checked in one query at most.
     *
     * @param  list<Page>  $pages
     * @return list<Page>
     */
    public function published(array $pages): array
    {
        $pages = array_values(array_filter($pages, fn (Page $page): bool => ! $page->hasStatus(PageStatus::UNPUBLISHED)));
        $offline = $this->belowUnpublished(array_map(fn (Page $page): int => (int) $page->id, $pages));

        return array_values(array_filter($pages, fn (Page $page): bool => ! in_array((int) $page->id, $offline, true)));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int> those with an unpublished page above them
     */
    private function belowUnpublished(array $ids): array
    {
        $branches = $this->branches->ids();

        if ($ids === [] || $branches === []) {
            return [];
        }

        // Unqualified columns in a single-table query, so the host's table prefix cannot break them.
        return Value::ints(DB::table(Tables::PAGE_CLOSURE)
            ->whereIn('descendant_id', $ids)
            ->whereIn('ancestor_id', $branches)
            ->where('depth', '>', 0)
            ->distinct()
            ->pluck('descendant_id')
            ->all());
    }
}
