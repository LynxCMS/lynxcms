<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\UnpublishedBranches;
use LynxCms\Core\Support\Tables;

/**
 * Which pages a selector leaves out by status: system pages always, hidden and unpublished pages
 * unless "include=hidden", "include=unpublished" or "include=all" brings them back.
 *
 * @internal
 */
final readonly class StatusFilter
{
    private const string PAGES = Tables::PAGES;

    public function __construct(private UnpublishedBranches $unpublishedBranches) {}

    /**
     * @param  list<string>  $include  the values of the selector's include clauses
     */
    public function apply(Builder $query, array $include): void
    {
        if (in_array('all', $include, true)) {
            return;
        }

        $excluded = PageStatus::SYSTEM;

        if (! in_array('hidden', $include, true)) {
            $excluded |= PageStatus::HIDDEN;
        }

        if (! in_array('unpublished', $include, true)) {
            $excluded |= PageStatus::UNPUBLISHED;
        }

        $query->whereIn(self::PAGES.'.status', $this->without($excluded));

        if (! in_array('unpublished', $include, true)) {
            $this->excludeBelowUnpublished($query);
        }
    }

    /**
     * Every status without the excluded flags. Listed rather than written as status & excluded = 0,
     * which PostgreSQL cannot estimate: it would expect a few hundred rows and sort a whole
     * template instead of reading an index in order (ADR-0061).
     *
     * @return list<int>
     */
    private function without(int $excluded): array
    {
        $all = array_sum(PageStatus::NAMES);

        return array_values(array_filter(range(0, $all), fn (int $status): bool => ($status & $excluded) === 0));
    }

    /**
     * Pages under an unpublished page are unpublished too (ADR-0026); hidden does not cascade.
     * Usually no unpublished page has children, and then no filter is added at all.
     */
    private function excludeBelowUnpublished(Builder $query): void
    {
        $branches = $this->unpublishedBranches->ids();

        if ($branches === []) {
            return;
        }

        $query->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))
            ->from(Tables::PAGE_CLOSURE)
            ->whereColumn(Tables::PAGE_CLOSURE.'.descendant_id', self::PAGES.'.id')
            ->whereIn(Tables::PAGE_CLOSURE.'.ancestor_id', $branches)
            ->where(Tables::PAGE_CLOSURE.'.depth', '>', 0));
    }
}
