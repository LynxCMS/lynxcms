<?php

declare(strict_types=1);

namespace LynxCms\Insights;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * When each page an insight is about was last changed: recorded with the insight, and compared
 * before its plan runs, so a plan never lands on a page edited since the report.
 *
 * @internal
 */
final readonly class PageBasis
{
    /**
     * @param  list<int>  $pages
     * @return array<int, string|null> page id => its last change; null for a page that no longer exists
     */
    public function of(array $pages): array
    {
        $times = DB::table(Tables::PAGES)->whereIn('id', $pages)->pluck('updated_at', 'id')->all();
        $basis = [];

        foreach ($pages as $page) {
            $basis[$page] = isset($times[$page]) ? Value::string($times[$page]) : null;
        }

        return $basis;
    }

    /**
     * The first page that changed or went away since the basis was recorded.
     *
     * @param  array<int|string, string|null>  $basis
     */
    public function changedPage(array $basis): ?int
    {
        $now = $this->of(array_map(intval(...), array_keys($basis)));

        foreach ($basis as $page => $time) {
            if ($now[(int) $page] === null || $now[(int) $page] !== $time) {
                return (int) $page;
            }
        }

        return null;
    }
}
