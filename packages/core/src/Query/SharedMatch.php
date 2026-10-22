<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Tables;

/**
 * Clauses on a shared field (single or multiple): the page has a row matching the condition,
 * written as id IN (pages with a matching row) so SQLite also starts from the (data, page_id)
 * index. An empty value in "=" matches pages without a non-empty value, rows or not; "!=" is the
 * exact opposite of "=", so "price!=" finds pages with a price and "tags!=a|b" pages with neither.
 *
 * @internal
 */
final readonly class SharedMatch
{
    public function __construct(private ClauseComparison $comparison) {}

    /**
     * @param  (callable(string): mixed)|null  $cast
     */
    public function apply(Builder $query, Field $field, Clause $clause, ?callable $cast, bool $isTextual): void
    {
        $likeAllowed = $isTextual && ! $field->fieldType()->multiple();

        if (! in_array($clause->operator, ['=', '!='], true)) {
            $query->whereIn(Tables::PAGES.'.id', $this->matching($field, $clause, $cast, $likeAllowed));

            return;
        }

        $equal = function (Builder $q) use ($field, $clause, $cast, $isTextual): void {
            $values = array_values(array_filter($clause->values, fn (string $value): bool => $value !== ''));

            if ($values !== []) {
                $q->orWhereIn(Tables::PAGES.'.id', $this->matching($field, new Clause($clause->field, '=', $values), $cast, false));
            }

            if (count($values) < count($clause->values)) {
                $q->orWhereNotIn(Tables::PAGES.'.id', $this->filled($field, $isTextual));
            }
        };

        $clause->operator === '=' ? $query->where($equal) : $query->whereNot($equal);
    }

    /**
     * Pages with a row matching the clause.
     *
     * @param  (callable(string): mixed)|null  $cast
     */
    private function matching(Field $field, Clause $clause, ?callable $cast, bool $likeAllowed): Builder
    {
        $table = $field->table();
        $matching = DB::table($table)->select("{$table}.page_id");
        $this->comparison->apply($matching, "{$table}.data", $clause, $cast, likeAllowed: $likeAllowed);

        return $matching;
    }

    /** Pages with a non-empty value. */
    private function filled(Field $field, bool $isTextual): Builder
    {
        $table = $field->table();

        return DB::table($table)->select("{$table}.page_id")->whereNotNull("{$table}.data")
            ->when($isTextual, fn (Builder $q) => $q->where("{$table}.data", '<>', ''));
    }
}
