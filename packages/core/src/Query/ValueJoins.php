<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Tables;

/**
 * The joins one compiled selector adds to lynx_pages, so a translatable field compares and sorts
 * by the value visitors actually see: COALESCE over left joins for each language in the fallback
 * chain (ADR-0005). Each field is joined once and its expression reused for sorting.
 *
 * @internal
 */
final class ValueJoins
{
    private const string PAGES = Tables::PAGES;

    /** @var array<string, string> field name (or @name) => SQL expression of its effective value */
    private array $joined = [];

    private int $alias = 0;

    /**
     * @param  list<int>  $chain  language ids, current language first
     */
    public function __construct(private readonly Builder $query, private readonly array $chain) {}

    /** A fresh table alias with the prefix, unique within the query. */
    public function alias(string $prefix): string
    {
        return $prefix.(++$this->alias);
    }

    /**
     * COALESCE over the field's rows in each chain language, or only the current language when
     * the field opts out of fallback.
     */
    public function effectiveValue(Field $field): Expression
    {
        return $this->coalesce($field->name, $field->table(), 'data', 'f', $this->chainOf($field));
    }

    /**
     * The languages the field's value falls back along, current language first.
     *
     * @return list<int>
     */
    public function chainOf(Field $field): array
    {
        return $field->fallbackMode() === 'none' ? [$this->chain[0]] : $this->chain;
    }

    /** The page's name in the first chain language that has one. */
    public function name(): Expression
    {
        return $this->coalesce('@name', Tables::PAGE_LANGUAGES, 'name', 'n', $this->chain);
    }

    /**
     * @param  list<int>  $languageIds
     */
    private function coalesce(string $key, string $table, string $column, string $prefix, array $languageIds): Expression
    {
        if (! isset($this->joined[$key])) {
            $grammar = DB::connection()->getQueryGrammar();
            $columns = [];

            foreach ($languageIds as $languageId) {
                $alias = $this->alias($prefix);
                $this->query->leftJoin("{$table} as {$alias}", function (JoinClause $join) use ($alias, $languageId): void {
                    $join->on("{$alias}.page_id", '=', self::PAGES.'.id')->where("{$alias}.language_id", '=', $languageId);
                });
                $columns[] = $grammar->wrap("{$alias}.{$column}");
            }

            // SQLite rejects COALESCE with a single argument (a language without fallback).
            $this->joined[$key] = count($columns) === 1 ? $columns[0] : 'COALESCE('.implode(', ', $columns).')';
        }

        return Sql::raw($this->joined[$key]);
    }
}
