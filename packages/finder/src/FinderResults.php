<?php

declare(strict_types=1);

namespace LynxCms\Finder;

use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Tree\TreeFilters;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Finder\Search\FinderQuery;

/**
 * Runs a finder search over the pages the page tree's filters cover (every status, without the
 * trash, system pages and module branches) and only those the current user may view.
 *
 * @internal
 */
final readonly class FinderResults
{
    public const int PER_PAGE = 25;

    public function __construct(private SelectorWriter $writer, private AdminAccess $access) {}

    /** The search as a selector a developer can reuse: its conditions and order. */
    public function selector(FinderQuery $query): string
    {
        $terms = $this->writer->terms($query);

        return ($terms === '' ? '' : "{$terms}, ")."sort={$query->sort}";
    }

    /** Up to $limit results, $start pages in. */
    public function find(FinderQuery $query, int $start = 0, int $limit = self::PER_PAGE): PageArray
    {
        return $this->run($this->selector($query).', start='.max(0, $start).', limit='.max(1, $limit));
    }

    /** A page the finder searches and the user may view, by id; null for any other. */
    public function page(int $id): ?Page
    {
        return $this->run('id='.max(0, $id).', limit=1')->first();
    }

    private function run(string $terms): PageArray
    {
        $scope = $this->access->viewScope();

        return lynx()->pages->find(new TreeFilters($scope)->within($terms), scope: $scope);
    }
}
