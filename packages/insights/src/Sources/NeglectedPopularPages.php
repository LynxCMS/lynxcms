<?php

declare(strict_types=1);

namespace LynxCms\Insights\Sources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LynxCms\Contracts\Metrics\PageMetrics;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\InsightSource;
use LynxCms\Insights\InsightText;

/**
 * The most viewed published pages that nobody changed in lynx-insights.neglected_months (12):
 * popular content that may be out of date. Needs page metrics (ADR-0156), so it reports nothing
 * without them. One insight that only informs, with the views it rests on.
 *
 * @internal
 */
final readonly class NeglectedPopularPages implements InsightSource
{
    /** The most viewed pages it looks at. */
    public const int TOP = 20;

    private const string KEY = 'lynx-insights::sources.neglected_pages';

    public function __construct(private Metrics $metrics, private PageFinder $finder) {}

    public function key(): string
    {
        return 'neglected-pages';
    }

    public function label(): string
    {
        return Value::string(__(self::KEY.'.label'));
    }

    public function insights(InsightPeriod $period): array
    {
        $metrics = $this->metrics->available();

        if (! $metrics instanceof PageMetrics) {
            return [];
        }

        $views = $metrics->top(PageMetrics::VIEW, $period->days(), self::TOP);
        $pages = $this->neglected(array_keys($views));

        if ($pages === []) {
            return [];
        }

        $total = array_sum(array_map(fn (int $page): int => $views[$page] ?? 0, $pages));
        $months = Value::int(config('lynx-insights.neglected_months'), 12);
        $replace = ['count' => count($pages), 'months' => $months, 'days' => $period->days()];

        return [new Insight(
            title: new InsightText(self::KEY.'.title', $replace, count($pages)),
            reason: new InsightText(self::KEY.'.reason', $replace),
            pages: $pages,
            evidence: [
                ['label' => new InsightText(self::KEY.'.evidence.views', $replace), 'value' => $total],
                ['label' => new InsightText(self::KEY.'.evidence.months'), 'value' => $months],
            ],
            weight: (float) $total,
            subject: 'neglected',
        )];
    }

    /**
     * The pages, in their order, that visitors can open and nobody changed in the months.
     *
     * @param  list<int>  $pages
     * @return list<int>
     */
    private function neglected(array $pages): array
    {
        if ($pages === []) {
            return [];
        }

        $published = array_flip($this->finder->ids('id='.implode('|', $pages).', include=hidden'));
        $cutoff = Carbon::now()->subMonths(Value::int(config('lynx-insights.neglected_months'), 12));
        $old = array_flip(Value::ints(DB::table(Tables::PAGES)->whereIn('id', $pages)->where('updated_at', '<', $cutoff)->pluck('id')->all()));

        return array_values(array_filter($pages, fn (int $page): bool => isset($published[$page], $old[$page])));
    }
}
