<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\Types\DateType;
use LynxCms\Core\Support\Value;
use Throwable;

/**
 * One selector clause as SQL: equality and emptiness, numeric comparisons and the LIKE operators,
 * with every value bound. Part of the selector compiler, the only place allowed to build trusted SQL.
 *
 * @internal
 */
final readonly class ClauseComparison
{
    /**
     * @param  (callable(string): mixed)|null  $cast
     */
    public function apply(Builder $query, string|Expression $column, Clause $clause, ?callable $cast = null, bool $likeAllowed = true): void
    {
        $cast ??= fn (string $v): string => $v;
        $values = array_map($cast, $clause->values);
        $sql = $column instanceof Expression ? (string) $column->getValue(DB::connection()->getQueryGrammar()) : DB::connection()->getQueryGrammar()->wrap($column);

        $query->where(function (Builder $q) use ($clause, $values, $sql, $likeAllowed): void {
            foreach ($values as $value) {
                match ($clause->operator) {
                    '=' => $this->equals($q, $sql, true, $value),
                    // "a!=x|y" means neither x nor y, as for shared fields and page columns.
                    '!=' => $this->equals($q, $sql, false, $value),
                    '<', '>', '<=', '>=' => $q->orWhereRaw(Sql::trusted("{$sql} {$clause->operator} ?"), [$value]),
                    default => $this->like($q, $sql, $clause->operator, $value, $likeAllowed),
                };
            }
        });
    }

    /**
     * = and !=, where an empty value means empty or missing. Values of = are alternatives (OR);
     * values of != must all differ (AND).
     */
    private function equals(Builder $q, string $sql, bool $isEqual, mixed $value): void
    {
        $boolean = $isEqual ? 'or' : 'and';

        if ($value === '' || $value === null) {
            $q->whereRaw(Sql::trusted($isEqual ? "({$sql} IS NULL OR {$sql} = '')" : "({$sql} IS NOT NULL AND {$sql} <> '')"), boolean: $boolean);

            return;
        }

        $q->whereRaw(Sql::trusted("{$sql} ".($isEqual ? '=' : '<>').' ?'), [$value], $boolean);
    }

    /** The LIKE operators (*=, %=, ^=, $=, and ~= for every word in any order), any case, with the value escaped. */
    private function like(Builder $q, string $sql, string $operator, mixed $value, bool $likeAllowed): void
    {
        if (! $likeAllowed) {
            throw new SelectorException("Operator [{$operator}] is not supported for this field.");
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(Value::string($value)));
        $patterns = match ($operator) {
            '^=' => [$escaped.'%'],
            '$=' => ['%'.$escaped],
            '~=' => array_map(fn (string $word): string => '%'.$word.'%', preg_split('/\s+/u', trim($escaped), flags: PREG_SPLIT_NO_EMPTY) ?: ['']),
            default => ['%'.$escaped.'%'],
        };
        $like = "lower({$sql}) LIKE ? ESCAPE '\\'";
        $q->orWhereRaw(Sql::trusted('('.implode(' AND ', array_fill(0, count($patterns), $like)).')'), $patterns);
    }

    /**
     * @return callable(string): mixed
     */
    public static function caster(Comparison $comparison): callable
    {
        return match ($comparison) {
            Comparison::Integer => fn (string $v): int => (int) $v,
            Comparison::Decimal => fn (string $v): float => (float) str_replace(',', '.', $v),
            Comparison::Boolean => fn (string $v): bool => in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true),
            Comparison::Date => self::date(...),
            default => fn (string $v): string => $v,
        };
    }

    /**
     * A selector date ("2026-01-01", "today", "-7 days", "2026-01-01 09:30") in the storage
     * format, so it compares with stored dates as text and as timestamps alike.
     */
    private static function date(string $value): string
    {
        try {
            return CarbonImmutable::parse($value)->format(DateType::STORAGE_FORMAT);
        } catch (Throwable) {
            throw new SelectorException("[{$value}] is not a date. Use a date such as 2026-01-01, or today, -7 days.");
        }
    }
}
