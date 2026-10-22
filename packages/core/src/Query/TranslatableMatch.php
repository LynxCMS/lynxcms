<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Tables;

/**
 * Exact and range clauses on a translatable field, matched on the value visitors see (ADR-0005)
 * without comparing a COALESCE, which no index can serve. A page matches when the first language
 * in the chain that has a value for it holds a matching one: one lookup per language, each served
 * by the field table's (language_id, data, page_id) index, joined into a list of page ids.
 *
 * Empty values, and the LIKE operators unless trigram indexes serve them, keep comparing the
 * effective value (ValueJoins).
 *
 * @internal
 */
final readonly class TranslatableMatch
{
    private const array OPERATORS = ['=', '<', '>', '<=', '>='];

    /** Matched language by language only with trigram indexes to serve them (ADR-0061). */
    private const array LIKE_OPERATORS = ['%=', '*=', '~=', '^=', '$='];

    public function __construct(private ClauseComparison $comparison) {}

    /** Whether the clause can be matched language by language. */
    public function handles(Clause $clause, bool $isTextual): bool
    {
        if (in_array('', $clause->values, true)) {
            return false;
        }

        return in_array($clause->operator, self::OPERATORS, true)
            || ($isTextual && config('lynx.trigram') === true && in_array($clause->operator, self::LIKE_OPERATORS, true));
    }

    /**
     * @param  list<int>  $languageIds  the chain the field falls back along, current language first
     * @param  (callable(string): mixed)|null  $cast
     */
    public function apply(Builder $query, Field $field, Clause $clause, array $languageIds, ?callable $cast): void
    {
        $pages = null;

        foreach ($languageIds as $position => $languageId) {
            $lookup = $this->lookup($field->table(), $clause, $languageId, array_slice($languageIds, 0, $position), $cast);
            $pages = $pages?->union($lookup) ?? $lookup;
        }

        $query->whereIn(Tables::PAGES.'.id', $pages ?? DB::query()->selectRaw('null'));
    }

    /**
     * Pages whose value in the language matches, and that have no value in an earlier language.
     *
     * @param  list<int>  $earlier
     * @param  (callable(string): mixed)|null  $cast
     */
    private function lookup(string $table, Clause $clause, int $languageId, array $earlier, ?callable $cast): Builder
    {
        $lookup = DB::table("{$table} as v")->select('v.page_id')->where('v.language_id', $languageId);
        $this->comparison->apply($lookup, 'v.data', $clause, $cast);

        if ($earlier !== []) {
            $lookup->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))->from("{$table} as e")
                ->whereColumn('e.page_id', 'v.page_id')
                ->whereIn('e.language_id', $earlier)
                ->whereNotNull('e.data'));
        }

        return $lookup;
    }
}
