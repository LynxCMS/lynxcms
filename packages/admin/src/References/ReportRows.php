<?php

declare(strict_types=1);

namespace LynxCms\Admin\References;

use LynxCms\Admin\Editor\LinkedPages;
use LynxCms\Admin\Editor\ReferenceRows;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Admin\Tree\TreeFilters;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\References\ReferenceReports;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Query\PageScope;
use LynxCms\Core\Support\LocalizedNumber;
use LynxCms\Core\Support\Value;

/**
 * A report's rows as the screen lists them: each page with its title, template and parent, what
 * the report says about it, and links to the fields that hold its references.
 *
 * @phpstan-type Row array{id: int, title: string, context: string, detail: string, links: list<array{label: string, url: string}>}
 *
 * @internal
 */
final readonly class ReportRows
{
    /** The most linked report lists this many pages, on one page. */
    public const int MOST_LINKED = 20;

    public function __construct(private ReferenceReports $reports, private ReferenceRows $references, private PageFinder $finder) {}

    /**
     * @return array{rows: list<Row>, total: int}
     */
    public function for(string $report, ?PageScope $scope, int $limit, int $start): array
    {
        return match ($report) {
            'unlinked' => $this->unlinked($scope, $limit, $start),
            'linked' => $this->linked($scope),
            default => $this->broken($scope, $limit, $start),
        };
    }

    /**
     * @return array{rows: list<Row>, total: int}
     */
    private function broken(?PageScope $scope, int $limit, int $start): array
    {
        $found = $this->reports->broken($scope, $limit, $start);
        $pages = $this->pages(array_map(fn (array $row): int => $row['reference']->pageId, $found['rows']));
        $targets = $this->pages(array_map(fn (array $row): int => $row['reference']->linkedPageId, $found['rows']));
        $titles = new DefaultPresenter;
        $rows = [];

        foreach ($found['rows'] as ['reference' => $reference, 'reason' => $reason]) {
            $page = $pages[$reference->pageId] ?? null;

            if ($page !== null) {
                $target = $targets[$reference->linkedPageId] ?? null;
                $rows[] = LinkedPages::row($page) + [
                    'detail' => Value::string(__('lynx-admin::page_references.broken.'.$reason, ['page' => $target === null ? '#'.$reference->linkedPageId : $titles->title($target)])),
                    'links' => [$this->references->location($page, $reference)],
                ];
            }
        }

        return ['rows' => $rows, 'total' => $found['total']];
    }

    /**
     * @return array{rows: list<Row>, total: int}
     */
    private function unlinked(?PageScope $scope, int $limit, int $start): array
    {
        $found = $this->reports->unlinked(new TreeFilters($scope)->within(''), $scope, $limit, $start);
        $pages = $this->pages($found['ids']);
        $rows = [];

        foreach ($found['ids'] as $id) {
            if (isset($pages[$id])) {
                $rows[] = LinkedPages::row($pages[$id]) + ['detail' => '', 'links' => []];
            }
        }

        return ['rows' => $rows, 'total' => $found['total']];
    }

    /**
     * @return array{rows: list<Row>, total: int}
     */
    private function linked(?PageScope $scope): array
    {
        $found = $this->reports->mostLinked($scope, self::MOST_LINKED);
        $pages = $this->pages(array_column($found, 'id'));
        $rows = [];

        foreach ($found as ['id' => $id, 'count' => $count]) {
            if (isset($pages[$id])) {
                $rows[] = LinkedPages::row($pages[$id]) + [
                    'detail' => trans_choice('lynx-admin::page_references.linked.count', $count, ['count' => LocalizedNumber::format($count)]),
                    'links' => [],
                ];
            }
        }

        return ['rows' => $rows, 'total' => count($rows)];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, Page> by id
     */
    private function pages(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        $pages = [];

        foreach ($this->finder->find('id='.implode('|', $ids).', include=all', maxLimit: count($ids)) as $page) {
            $pages[(int) $page->id] = $page;
        }

        return $pages;
    }
}
