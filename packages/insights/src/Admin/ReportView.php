<?php

declare(strict_types=1);

namespace LynxCms\Insights\Admin;

use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Models\StoredInsight;

/**
 * What the Insights screen shows: the latest report with its insights, heaviest first, each with
 * the pages it is about that the user may view, and the earlier reports with how their insights
 * were decided.
 *
 * @phpstan-type PageLink array{id: int, title: string}
 * @phpstan-type Earlier array{report: InsightReport, counts: array<string, int>}
 *
 * @internal
 */
final readonly class ReportView
{
    /** Earlier reports listed below the latest one. */
    public const int EARLIER = 12;

    public function __construct(private AdminAccess $access, private PageLoader $loader) {}

    public function latest(): ?InsightReport
    {
        return InsightReport::query()->latest('id')->first();
    }

    /** @return list<StoredInsight> */
    public function insights(InsightReport $report): array
    {
        return array_values($report->insights()->orderByDesc('weight')->orderBy('id')->get()->all());
    }

    /**
     * The pages of each insight the user may view, by insight id.
     *
     * @param  list<StoredInsight>  $insights
     * @return array<int, list<PageLink>>
     */
    public function pages(array $insights): array
    {
        $ids = array_values(array_unique(array_merge([], ...array_map(fn (StoredInsight $insight): array => $insight->pages, $insights))));
        $titles = $this->titles($this->access->viewable($ids));
        $links = [];

        foreach ($insights as $insight) {
            $links[$insight->id] = array_values(array_filter(array_map(
                fn (int $id): ?array => isset($titles[$id]) ? ['id' => $id, 'title' => $titles[$id]] : null,
                $insight->pages,
            )));
        }

        return $links;
    }

    /** @return list<Earlier> */
    public function earlier(InsightReport $latest): array
    {
        $reports = InsightReport::query()->where('id', '<', $latest->id)->latest('id')->limit(self::EARLIER)->get();

        return array_values($reports->map(fn (InsightReport $report): array => ['report' => $report, 'counts' => $this->counts($report)])->all());
    }

    public function openCount(): int
    {
        return StoredInsight::query()->where('state', InsightState::Open->value)->count();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function titles(array $ids): array
    {
        $presenter = new DefaultPresenter;
        $titles = [];

        foreach ($this->loader->many($ids) as $page) {
            $titles[(int) $page->id] = $presenter->title($page);
        }

        return $titles;
    }

    /** @return array<string, int> state => how many */
    private function counts(InsightReport $report): array
    {
        $counts = [];

        foreach ($report->insights()->toBase()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state') as $state => $total) {
            $counts[(string) $state] = Value::int($total);
        }

        return $counts;
    }
}
