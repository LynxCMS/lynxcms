<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Pages without a URL (slides, promos, settings, forms) only show inside other pages, so a
 * selector leaves them out unless it asks for them (ADR-0096): by naming a template without a URL,
 * by reading below a page without one (parent, has_parent, children()), by id, or with
 * include=no_url or include=all.
 *
 * @internal
 */
final readonly class UrlFilter
{
    private const string PAGES = Tables::PAGES;

    /** Clauses that read below the pages they name. */
    private const array BELOW = ['parent', 'has_parent'];

    public function __construct(private TemplateRegistry $templates) {}

    /**
     * @param  list<Clause>  $clauses
     * @param  list<string>  $include  the values of the selector's include clauses
     * @param  Closure(string): int  $pageId  resolves a page reference (id or path) the way the selector does
     */
    public function apply(Builder $query, array $clauses, array $include, Closure $pageId): void
    {
        if (in_array('all', $include, true) || in_array('no_url', $include, true)) {
            return;
        }

        $withoutUrl = $this->templatesWithoutUrl();

        if ($withoutUrl === [] || $this->asksFor($clauses, $withoutUrl, $pageId)) {
            return;
        }

        $query->whereNotIn(self::PAGES.'.template_id', $withoutUrl);
    }

    /** @return list<int> */
    private function templatesWithoutUrl(): array
    {
        return Value::ints($this->templates->all()->reject(fn (Template $template): bool => $template->hasUrl())->pluck('id')->values()->all());
    }

    /**
     * @param  list<Clause>  $clauses
     * @param  list<int>  $withoutUrl
     * @param  Closure(string): int  $pageId
     */
    private function asksFor(array $clauses, array $withoutUrl, Closure $pageId): bool
    {
        foreach ($clauses as $clause) {
            if ($clause->operator === '!=') {
                continue;
            }

            $isAsked = match (true) {
                $clause->field === 'id' => true,
                $clause->field === 'template' => array_intersect($this->templateIds($clause), $withoutUrl) !== [],
                in_array($clause->field, self::BELOW, true) => $this->isBelowPageWithoutUrl($clause, $withoutUrl, $pageId),
                default => false,
            };

            if ($isAsked) {
                return true;
            }
        }

        return false;
    }

    /** @return list<int> */
    private function templateIds(Clause $clause): array
    {
        return array_map(fn (string $name): int => $this->templates->find($name)->id ?? 0, $clause->values);
    }

    /**
     * @param  list<int>  $withoutUrl
     * @param  Closure(string): int  $pageId
     */
    private function isBelowPageWithoutUrl(Clause $clause, array $withoutUrl, Closure $pageId): bool
    {
        $ids = array_filter(array_map($pageId, $clause->values));

        return $ids !== [] && DB::table(self::PAGES)->whereIn('id', $ids)->whereIn('template_id', $withoutUrl)->exists();
    }
}
