<?php

declare(strict_types=1);

namespace LynxCms\Insights\Sources;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * How many other pages link to each page (the page references index, ADR-0145), so a source
 * without metrics puts the most linked pages first. Lynx's own sources share it, the SEO module's
 * included.
 *
 * @internal
 */
final readonly class InboundLinks
{
    /** Ids per query, below every driver's limit on bound values. */
    private const int CHUNK = 500;

    /**
     * @param  list<int>  $pages
     * @return array<int, int> page id => the pages linking to it; pages nothing links to are left out
     */
    public function counts(array $pages): array
    {
        $links = [];

        foreach (array_chunk($pages, self::CHUNK) as $chunk) {
            $rows = DB::table(Tables::PAGE_REFERENCES)->whereIn('linked_page_id', $chunk)->whereColumn('page_id', '!=', 'linked_page_id')
                ->groupBy('linked_page_id')->selectRaw('linked_page_id, count(distinct page_id) as total')->pluck('total', 'linked_page_id')->all();

            foreach ($rows as $page => $total) {
                $links[(int) $page] = Value::int($total);
            }
        }

        return $links;
    }

    /**
     * The pages, most linked first, then by id.
     *
     * @param  list<int>  $pages
     * @param  array<int, int>  $counts  from counts()
     * @return list<int>
     */
    public function sort(array $pages, array $counts): array
    {
        usort($pages, fn (int $a, int $b): int => [$counts[$b] ?? 0, $a] <=> [$counts[$a] ?? 0, $b]);

        return $pages;
    }
}
