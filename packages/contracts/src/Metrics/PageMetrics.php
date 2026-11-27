<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Metrics;

use DateTimeInterface;

/**
 * Counts of what visitors did on pages, kept by the site or a module (ADR-0156). Lynx stores no
 * metrics: a site binds this to its own counts, and features that need them (Insights' sources)
 * skip their work when nothing is bound. Counts are daily totals per page and event; an event is
 * a name the site counts, "view" at least.
 *
 * @api
 */
interface PageMetrics
{
    /** The event every implementation counts: a page shown to a visitor. */
    public const string VIEW = 'view';

    /**
     * Each page's total for the event over the last $days days, today included.
     *
     * @param  list<int>  $pageIds
     * @return array<int, int> page id => count; pages without a count may be left out
     */
    public function totals(array $pageIds, string $event, int $days): array;

    /**
     * The pages with the highest totals for the event over the last $days days, highest first.
     *
     * @return array<int, int> page id => count, at most $limit pages
     */
    public function top(string $event, int $days, int $limit): array;

    /** The first day with any count, or null before the first one. */
    public function since(): ?DateTimeInterface;
}
