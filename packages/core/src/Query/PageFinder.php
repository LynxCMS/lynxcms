<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Runs selectors: lynx()->pages->find('template=course, price<300, sort=-title, limit=10').
 * Filtering and sorting on translatable fields use the value visitors see in the current
 * language (or "language=pt" in the selector).
 *
 * @api
 */
final readonly class PageFinder
{
    public function __construct(
        private SelectorParser $parser,
        private SelectorCompiler $compiler,
        private PageLoader $loader,
        private LanguageContext $context,
        private LanguageRegistry $languages,
        private PageVisibility $visibility,
    ) {}

    /**
     * @param  list<string>  $with  page-reference fields to eager load
     * @param  int|null  $maxLimit  upper bound for "limit=" in the selector, for selectors that come
     *                              from agents or visitors; also the limit when the selector has none
     * @param  int|null  $defaultLimit  limit when the selector has none (capped by $maxLimit)
     * @param  PageScope|null  $scope  only pages these grants cover; results and total both respect it
     */
    public function find(string $selector, array $with = [], ?int $maxLimit = null, ?int $defaultLimit = null, ?PageScope $scope = null): PageArray
    {
        $compiled = $this->compile($selector);
        $scope?->apply($compiled->query);
        $scope?->apply($compiled->countQuery);
        $query = $compiled->query;
        $limit = $this->effectiveLimit($compiled->limit, $maxLimit, $defaultLimit);

        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($compiled->start > 0) {
            $query->offset($compiled->start)->limit($limit ?? PHP_INT_MAX);
        }

        $ids = array_values(array_unique(Value::ints($query->pluck('id')->all())));
        $total = $limit === null && $compiled->start === 0 ? count($ids) : fn (): int => $this->countCompiled($compiled);

        return new PageArray($this->loader->many($ids, $with, $compiled->join)->all(), $total);
    }

    /**
     * The limit a query runs with: the selector's own, or the default, never above the maximum.
     */
    public function effectiveLimit(?int $selectorLimit, ?int $maxLimit, ?int $defaultLimit = null): ?int
    {
        $limit = $selectorLimit ?? $defaultLimit ?? $maxLimit;

        return $maxLimit === null || $limit === null ? $limit : min($limit, max(1, $maxLimit));
    }

    public function findOne(string $selector): ?Page
    {
        return $this->find(trim($selector.', limit=1', ', '))->first();
    }

    /**
     * @return list<int>
     */
    public function ids(string $selector): array
    {
        $compiled = $this->compile($selector);

        if ($compiled->limit !== null) {
            $compiled->query->limit($compiled->limit);
        }

        return array_values(array_unique(Value::ints($compiled->query->pluck('id')->all())));
    }

    public function count(string $selector): int
    {
        return $this->countCompiled($this->compile($selector));
    }

    /**
     * Number of matching children per parent, in one grouped query. Parents without
     * matches get 0. The selector adds filters such as "include=all".
     *
     * @param  list<int>  $parentIds
     * @param  PageScope|null  $scope  count only the pages these grants cover
     * @return array<int, int>
     */
    public function countByParent(array $parentIds, string $selector = '', ?PageScope $scope = null): array
    {
        if ($parentIds === []) {
            return [];
        }

        $compiled = $this->compile(trim('parent='.implode('|', $parentIds).', '.$selector, ', '));
        $scope?->apply($compiled->countQuery);
        $counts = $compiled->countQuery
            ->select(Tables::PAGES.'.parent_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy(Tables::PAGES.'.parent_id')
            ->pluck('aggregate', 'parent_id')
            ->all();

        $result = [];

        foreach ($parentIds as $id) {
            $result[$id] = Value::int($counts[$id] ?? 0);
        }

        return $result;
    }

    public function compile(string $selector): CompiledSelector
    {
        $clauses = $this->parser->parse($selector);
        $chain = $this->context->chainIds();

        foreach ($clauses as $clause) {
            if (in_array($clause->field, ['language', 'lang'], true)) {
                $chain = $this->languages->chainIds($clause->value());
            }
        }

        $compiled = $this->compiler->compile($clauses, $chain);
        $this->visibility->apply($compiled->query);
        $this->visibility->apply($compiled->countQuery);

        return $compiled;
    }

    private function countCompiled(CompiledSelector $compiled): int
    {
        return (int) $compiled->countQuery->distinct()->count(Tables::PAGES.'.id');
    }
}
