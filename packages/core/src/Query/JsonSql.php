<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Contracts\Fields\Comparison;

/**
 * JSON in item field rows (ADR-0044), on SQLite and PostgreSQL. Keys are field names, checked
 * here, so they may appear in the SQL; values are always bound. Laravel's whereJsonContains
 * breaks on SQLite under a table prefix (it prefixes json_each), hence this class.
 *
 * @internal
 */
final class JsonSql
{
    /** The alias of the elements a nested list is read into (elements()). */
    public const string ELEMENT = 'e';

    /** "The JSON list under $key holds $value." */
    public static function contains(Builder $query, string $column, string $key, int|string $value, string $boolean = 'and'): void
    {
        self::assertKey($key);
        $wrapped = DB::connection()->getQueryGrammar()->wrap($column);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->whereRaw(Sql::trusted("({$wrapped}::jsonb -> '{$key}') @> ?::jsonb"), [json_encode([$value], JSON_THROW_ON_ERROR)], $boolean);

            return;
        }

        $query->whereRaw(Sql::trusted("EXISTS (SELECT 1 FROM json_each({$wrapped}, '\$.\"{$key}\"') WHERE json_each.value = ?)"), [$value], $boolean);
    }

    /** SQL for the scalar under $key, typed for comparing as $comparison. */
    public static function extract(string $column, string $key, Comparison $comparison): string
    {
        self::assertKey($key);
        $wrapped = DB::connection()->getQueryGrammar()->wrap($column);

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return "json_extract({$wrapped}, '\$.\"{$key}\"')";
        }

        $text = "({$wrapped}::jsonb ->> '{$key}')";

        return match ($comparison) {
            Comparison::Integer => "{$text}::numeric",
            Comparison::Boolean => "{$text}::boolean",
            default => $text,
        };
    }

    /**
     * FROM SQL for the elements of the JSON list under $key (a field nested in an item, such as
     * images in a matrix block), each element's JSON in "e.value".
     */
    public static function elements(string $column, string $key): string
    {
        self::assertKey($key);
        $wrapped = DB::connection()->getQueryGrammar()->wrap($column);
        $alias = self::ELEMENT;

        return DB::connection()->getDriverName() === 'pgsql'
            ? "jsonb_array_elements({$wrapped}::jsonb -> '{$key}') AS {$alias}(value)"
            : "json_each({$wrapped}, '\$.\"{$key}\"') AS {$alias}";
    }

    /** SQL true when the current element (elements()) is switched on. */
    public static function elementEnabled(): string
    {
        $alias = self::ELEMENT;

        return DB::connection()->getDriverName() === 'pgsql'
            ? "({$alias}.value ->> 'enabled')::boolean"
            : "json_extract({$alias}.value, '\$.enabled') = 1";
    }

    /**
     * SQL for a scalar in the current element's values, typed for $comparison; $language picks
     * one language of a translated value ({"pt": "…"}).
     */
    public static function elementValue(string $key, Comparison $comparison, ?string $language = null): string
    {
        self::assertKey($key);
        $alias = self::ELEMENT;

        if ($language !== null && preg_match('/^[A-Za-z0-9_-]+$/', $language) !== 1) {
            throw new SelectorException("[{$language}] is not a language code.");
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return "json_extract({$alias}.value, '\$.values.\"{$key}\"".($language === null ? '' : ".\"{$language}\"")."')";
        }

        $text = $language === null ? "({$alias}.value -> 'values' ->> '{$key}')" : "({$alias}.value -> 'values' -> '{$key}' ->> '{$language}')";

        return $comparison === Comparison::Integer ? "{$text}::numeric" : $text;
    }

    /** "The list under $key in the current element's values holds $value." */
    public static function elementContains(Builder $query, string $key, string $value, string $boolean = 'and'): void
    {
        self::assertKey($key);
        $alias = self::ELEMENT;

        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->whereRaw(Sql::trusted("({$alias}.value -> 'values' -> '{$key}') @> ?::jsonb"), [json_encode([$value], JSON_THROW_ON_ERROR)], $boolean);

            return;
        }

        $query->whereRaw(Sql::trusted("EXISTS (SELECT 1 FROM json_each({$alias}.value, '\$.values.\"{$key}\"') AS t WHERE t.value = ?)"), [$value], $boolean);
    }

    private static function assertKey(string $key): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
            throw new SelectorException("[{$key}] is not a field name.");
        }
    }
}
