<?php

declare(strict_types=1);

namespace LynxCms\Finder;

use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Tree\TreeFilters;
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

    /** One page of results, $start pages in. */
    public function find(FinderQuery $query, int $start = 0): PageArray
    {
        $scope = $this->access->viewScope();
        $selector = new TreeFilters($scope)->within($this->selector($query)).', start='.max(0, $start).', limit='.self::PER_PAGE;

        return lynx()->pages->find($selector, scope: $scope);
    }
}
