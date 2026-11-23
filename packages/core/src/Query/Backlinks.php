<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Which pages reference a page in a field their template uses (ADR-0042), read from the page
 * references index (ADR-0145).
 *
 * @internal
 */
final readonly class Backlinks
{
    /** Targets per query, below every database's limit on bound values. */
    private const int CHUNK = 500;

    /**
     * @return list<int> ids of the referencing pages, lowest first
     */
    public function pageIds(int $pageId): array
    {
        return $this->referencing([$pageId]);
    }

    /**
     * Pages that reference any of $targets, whatever their status, the targets themselves included.
     *
     * @param  list<int>  $targets
     * @return list<int> ids of the referencing pages, lowest first
     */
    public function referencing(array $targets): array
    {
        $ids = [];

        foreach (array_chunk(array_values(array_unique($targets)), self::CHUNK) as $chunk) {
            array_push($ids, ...Value::ints(DB::table(Tables::PAGE_REFERENCES)->whereIn('linked_page_id', $chunk)->distinct()->pluck('page_id')->all()));
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
