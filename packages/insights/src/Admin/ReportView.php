<?php

declare(strict_types=1);

namespace LynxCms\Insights\Admin;

use Illuminate\Support\Str;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Models\StoredInsight;

/**
 * What the Insights screen shows: one report (the latest unless another is asked for), its
 * insights that pass the filters, heaviest first, each with the pages it is about that the user
 * may view. The search runs here, on the translated wording, since a report holds few insights
 * and its wording is only known in the reader's language.
 *
 * @phpstan-type PageLink array{id: int, title: string}
 * @phpstan-type Shown array{insights: list<StoredInsight>, pages: array<int, list<PageLink>>}
 *
 * @internal
 */
final readonly class ReportView
{
    /** The reports the report filter offers, newest first. */
    public const int REPORTS = 12;

    public function __construct(private AdminAccess $access, private PageLoader $loader, private InsightSourceRegistry $sources) {}

    /** The report asked for, else the latest; null before the first report. */
    public function report(?int $id): ?InsightReport
    {
        $asked = $id !== null ? InsightReport::query()->whereKey($id)->first() : null;

        return $asked ?? InsightReport::query()->latest('id')->first();
    }

    /** @return list<InsightReport> */
    public function reports(): array
    {
        return array_values(InsightReport::query()->latest('id')->limit(self::REPORTS)->get()->all());
    }

    /**
     * The report's insights that pass the filters, with their pages.
     *
     * @return Shown
     */
    public function shown(InsightReport $report, InsightFilter $filter): array
    {
        $query = $report->insights()->orderByDesc('weight')->orderBy('id');

        $query->whereIn('state', $filter->covers());

        if ($filter->source !== '') {
            $query->where('source', $filter->source);
        }

        $insights = array_values($query->get()->all());
        $pages = $this->pages($insights);

        if ($filter->search !== '') {
            $labels = $this->labels();
            $insights = array_values(array_filter($insights, fn (StoredInsight $insight): bool => $this->matches($insight, $labels, $pages[$insight->id] ?? [], $filter->search)));
        }

        return ['insights' => $insights, 'pages' => $pages];
    }

    /** @return array<string, int> state => how many of the report's insights */
    public function counts(InsightReport $report): array
    {
        $counts = [];

        foreach ($report->insights()->toBase()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state') as $state => $total) {
            $counts[(string) $state] = Value::int($total);
        }

        // Insights being approved count as open, which is where the screen lists them.
        $counts[InsightState::Open->value] = ($counts[InsightState::Open->value] ?? 0) + ($counts[InsightState::Approving->value] ?? 0);
        unset($counts[InsightState::Approving->value]);

        return $counts;
    }

    /** @return array<string, string> the report's sources, key => label */
    public function sources(InsightReport $report): array
    {
        $labels = $this->labels();
        $keys = Value::strings($report->insights()->toBase()->distinct()->orderBy('source')->pluck('source')->all());

        return array_combine($keys, array_map(fn (string $key): string => $labels[$key] ?? $key, $keys));
    }

    /** @return array<string, string> every registered source, key => label */
    public function labels(): array
    {
        $labels = [];

        foreach ($this->sources->all() as $source) {
            $labels[$source->key()] = $source->label();
        }

        return $labels;
    }

    public function isApproving(InsightReport $report): bool
    {
        return $report->insights()->where('state', InsightState::Approving->value)->exists();
    }

    public function openCount(): int
    {
        return StoredInsight::query()->where('state', InsightState::Open->value)->count();
    }

    /**
     * @param  list<StoredInsight>  $insights
     * @return array<int, list<PageLink>> the pages of each insight the user may view, by insight id
     */
    private function pages(array $insights): array
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

    /**
     * @param  array<string, string>  $labels
     * @param  list<PageLink>  $pages
     */
    private function matches(StoredInsight $insight, array $labels, array $pages, string $search): bool
    {
        $text = [$insight->title->translate(), $insight->reason->translate(), $labels[$insight->source] ?? $insight->source, ...array_column($pages, 'title')];

        return Str::contains(implode("\n", $text), $search, ignoreCase: true);
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
}
