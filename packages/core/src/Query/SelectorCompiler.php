<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Indexes\SortKeys;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\PathResolver;
use LynxCms\Core\Search\SearchIndex;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Compiles selector clauses to a Query Builder query over lynx_pages (ADR-0010).
 *
 * Shared fields compile to EXISTS subqueries. Translatable fields compare the value visitors
 * actually see, through ValueJoins (ADR-0005).
 *
 * @internal
 */
final class SelectorCompiler
{
    private const string PAGES = Tables::PAGES;

    /** Names that are not fields and cannot be combined with others ("title|sort=x"). */
    private const array RESERVED = ['sort', 'limit', 'start', 'include', 'join', 'language', 'lang', 'search', ...PageStateMatch::TERMS];

    private ValueJoins $joins;

    public function __construct(
        private readonly FieldRegistry $fields,
        private readonly TemplateRegistry $templates,
        private readonly LanguageRegistry $languages,
        private readonly PathResolver $paths,
        private readonly StatusFilter $status,
        private readonly UrlFilter $urls,
        private readonly ClauseComparison $comparison,
        private readonly SubfieldCompiler $subfields,
        private readonly SearchIndex $search,
        private readonly TranslatableMatch $translatable,
        private readonly CountClause $counts,
        private readonly SharedMatch $shared,
        private readonly SortKeys $sortKeys,
        private readonly PageStateMatch $state,
    ) {}

    /**
     * @param  list<Clause>  $clauses
     * @param  list<int>  $chain  language ids, current language first
     */
    public function compile(array $clauses, array $chain): CompiledSelector
    {
        // Drafts (ADR-0039) are never found: they are versions of a page, not pages.
        $query = DB::table(self::PAGES)->select(self::PAGES.'.id')->whereNull(self::PAGES.'.draft_of');
        $this->joins = new ValueJoins($query, $chain);
        $limit = null;
        $start = 0;
        $sorts = [];
        $include = [];
        $join = [];

        foreach ($clauses as $clause) {
            if (PageStateMatch::handles($clause)) {
                $include = [...$include, ...$this->state->apply($query, $clause)];

                continue;
            }

            match ($clause->field) {
                'limit' => $limit = max(0, (int) $clause->value()),
                'start' => $start = max(0, (int) $clause->value()),
                'sort' => $sorts = [...$sorts, ...$clause->values],
                'include' => $include = [...$include, ...$clause->values],
                'join' => $join = [...$join, ...$this->joinedFields($clause)],
                'language', 'lang' => null, // handled by PageFinder
                'search' => $this->search->apply($query, $clause, $chain[0]),
                default => $this->where($query, $clause, $chain),
            };
        }

        $this->status->apply($query, $include);
        $this->urls->apply($query, $clauses, $include, $this->pageId(...));

        $countQuery = clone $query;

        foreach ($sorts === [] ? ['sort', 'id'] : $sorts as $sort) {
            $this->orderBy($query, $sort);
        }

        return new CompiledSelector($query, $countQuery, $limit, $start, $join);
    }

    /**
     * "join=summary|body" (ADR-0067): fields to load up front with the found pages, or "all".
     *
     * @return list<string>
     */
    private function joinedFields(Clause $clause): array
    {
        $names = array_values(array_map(fn (mixed $value): string => trim(Value::string($value)), $clause->values));

        foreach ($names as $name) {
            if ($name !== 'all' && ! $this->fields->find($name) instanceof Field) {
                throw new SelectorException("Cannot join [{$name}]: there is no such field.");
            }
        }

        return $names;
    }

    /**
     * @param  list<int>  $chain
     */
    private function where(Builder $query, Clause $clause, array $chain): void
    {
        if (str_contains($clause->field, '|')) {
            $this->whereAnyField($query, $clause, $chain);

            return;
        }

        if (CountClause::matches($clause)) {
            $this->counts->apply($query, $clause, self::PAGES.'.id');

            return;
        }

        if ($this->wherePageColumn($query, $clause)) {
            return;
        }

        if (str_contains($clause->field, '.')) {
            $this->subfields->apply($query, $clause, $chain, $this->pageId(...), self::PAGES.'.id');

            return;
        }

        $this->whereField($query, $clause);
    }

    /**
     * "title|summary%=risk" (ADR-0060): any of the fields matches; with != none of them holds the value.
     *
     * @param  list<int>  $chain
     */
    private function whereAnyField(Builder $query, Clause $clause, array $chain): void
    {
        $boolean = $clause->operator === '!=' ? 'and' : 'or';

        $query->where(function (Builder $any) use ($clause, $chain, $boolean): void {
            foreach (explode('|', $clause->field) as $field) {
                if (in_array($field, self::RESERVED, true)) {
                    throw new SelectorException("[{$field}] cannot be combined with other fields in [{$clause->field}].");
                }

                $any->where(fn (Builder $one) => $this->where($one, new Clause($field, $clause->operator, $clause->values), $chain), boolean: $boolean);
            }
        });
    }

    /**
     * Clauses on the page's own columns (id, template, parent, has_parent, name and dates).
     *
     * @return bool whether the clause was one
     */
    private function wherePageColumn(Builder $query, Clause $clause): bool
    {
        switch ($clause->field) {
            case 'id':
                $this->comparison->apply($query, self::PAGES.'.id', $clause, fn (string $v): int => (int) $v, likeAllowed: false);

                return true;
            case 'template':
                $ids = array_map(fn (string $name): int => $this->templates->find($name)->id ?? 0, $clause->values);
                $clause->operator === '!=' ? $query->whereNotIn(self::PAGES.'.template_id', $ids) : $query->whereIn(self::PAGES.'.template_id', $ids);

                return true;
            case 'parent':
                $ids = array_map($this->pageId(...), $clause->values);
                $clause->operator === '!=' ? $query->whereNotIn(self::PAGES.'.parent_id', $ids) : $query->whereIn(self::PAGES.'.parent_id', $ids);

                return true;
            case 'has_parent':
                $ids = array_map($this->pageId(...), $clause->values);
                $method = $clause->operator === '!=' ? 'whereNotExists' : 'whereExists';
                $query->{$method}(fn (Builder $q) => $q->select(DB::raw(1))->from(Tables::PAGE_CLOSURE)
                    ->whereColumn(Tables::PAGE_CLOSURE.'.descendant_id', self::PAGES.'.id')
                    ->whereIn(Tables::PAGE_CLOSURE.'.ancestor_id', $ids)
                    ->where(Tables::PAGE_CLOSURE.'.depth', '>', 0));

                return true;
            case 'name':
                $this->comparison->apply($query, $this->joins->name(), $clause);

                return true;
            case 'created':
            case 'modified':
            case 'published':
                $column = ['created' => 'created_at', 'modified' => 'updated_at', 'published' => 'published_at'][$clause->field];
                $this->comparison->apply($query, self::PAGES.'.'.$column, $clause, fn (string $v): string => date('Y-m-d H:i:s', strtotime($v) ?: 0), likeAllowed: false);

                return true;
        }

        return false;
    }

    /**
     * A clause on a field: translatable fields compare their effective value, shared ones match
     * through EXISTS.
     */
    private function whereField(Builder $query, Clause $clause): void
    {
        $field = $this->fields->find($clause->field)
            ?? throw new SelectorException("Unknown field [{$clause->field}] in selector.");

        $type = $field->fieldType();

        if ($type->comparison() === Comparison::None) {
            throw new SelectorException("Fields of type [{$field->type}] such as [{$field->name}] cannot be used in selectors.");
        }

        $cast = ClauseComparison::caster($type->comparison());
        $textual = $type->comparison() === Comparison::Text;

        if ($field->isTranslatable()) {
            $this->translatable->handles($clause, $textual)
                ? $this->translatable->apply($query, $field, $clause, $this->joins->chainOf($field), $cast)
                : $this->comparison->apply($query, $this->joins->effectiveValue($field), $clause, $cast, likeAllowed: $textual);

            return;
        }

        $this->shared->apply($query, $field, $clause, $cast, $textual);
    }

    private function orderBy(Builder $query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $name = ltrim($sort, '-');

        $column = match ($name) {
            'id' => self::PAGES.'.id',
            'sort' => self::PAGES.'.sort',
            'created' => self::PAGES.'.created_at',
            'modified' => self::PAGES.'.updated_at',
            'published' => self::PAGES.'.published_at',
            'name' => $this->joins->name(),
            default => null,
        };

        if ($column === null) {
            $field = $this->fields->find($name) ?? throw new SelectorException("Unknown sort field [{$name}].");

            if ($field->fieldType()->multiple() || $field->type === 'component') {
                throw new SelectorException("Cannot sort by [{$name}].");
            }

            if ($this->sortKeys->covers($field)) {
                $column = $this->sortKeys->join($query, $field, $this->joins->chainOf($field)[0], $this->joins->alias('k'));
            } elseif ($field->isTranslatable()) {
                $column = $this->joins->effectiveValue($field);
            } else {
                $alias = $this->joins->alias('s');
                $query->leftJoin("{$field->table()} as {$alias}", "{$alias}.page_id", '=', self::PAGES.'.id');
                $column = "{$alias}.data";
            }
        }

        $column instanceof Expression ? $query->orderByRaw(Sql::trusted($column->getValue(DB::connection()->getQueryGrammar()).' '.$direction)) : $query->orderBy($column, $direction);
    }

    private function pageId(string $reference): int
    {
        if (ctype_digit($reference)) {
            return (int) $reference;
        }

        return $this->paths->resolve($this->languages->default(), $reference)[0] ?? 0;
    }
}
