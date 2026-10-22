<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\Items\HasItemProperties;
use LynxCms\Core\Fields\Items\ItemProperty;
use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Languages\LanguageRegistry;

/**
 * Selector clauses on what items hold beyond subfields (ADR-0049): "gallery.tags=hero" on the
 * item rows "s" of a field with HasItemProperties, and "blocks.gallery.count>0" or
 * "blocks.gallery.tags=hero" on such a field nested in repeater or matrix items. Only switched-on
 * files count. Part of the selector compiler, so it may build trusted SQL.
 *
 * @internal
 */
final readonly class ItemPropertyCompiler
{
    public function __construct(private ClauseComparison $comparison, private LanguageRegistry $languages) {}

    /**
     * A property of the item rows "s" of $table.
     *
     * @param  list<int>  $chain  language ids to read translatable properties in
     */
    public function matchRow(Builder $q, string $table, string $name, ItemProperty $property, string $fallbackMode, Clause $clause, array $chain): void
    {
        if ($property->isList) {
            $this->assertListOperator($name, $clause);
            $q->where(function (Builder $any) use ($name, $clause): void {
                foreach ($clause->values as $value) {
                    JsonSql::contains($any, 's.data', $name, $value, 'or');
                }
            });

            return;
        }

        $expression = $property->isTranslatable
            ? $this->fallbackValue($q, $table, $name, $fallbackMode, $property->comparison, $chain)
            : JsonSql::extract('s.data', $name, $property->comparison);

        $this->compare($q, $expression, $property->comparison, $clause);
    }

    /**
     * A property, or "count", of the field $subfield nested in the item rows "s".
     *
     * @param  list<int>  $chain
     */
    public function matchNested(Builder $q, Subfield $subfield, string $name, Clause $clause, array $chain): void
    {
        $type = $subfield->type();

        if (! $type instanceof HasItemProperties) {
            throw new SelectorException("Subfield [{$subfield->name()}] holds no items to select by.");
        }

        $elements = JsonSql::elements('s.data', $subfield->name());
        $enabled = JsonSql::elementEnabled();

        if ($name === 'count') {
            $this->compare($q, "(SELECT COUNT(*) FROM {$elements} WHERE {$enabled})", Comparison::Integer, $clause);

            return;
        }

        $property = $type->itemProperties()[$name] ?? throw new SelectorException(
            "Subfield [{$subfield->name()}] has no property [{$name}]. Use count or one of: ".implode(', ', array_keys($type->itemProperties())).'.',
        );

        $q->whereExists(function (Builder $e) use ($elements, $enabled, $name, $property, $clause, $chain, $subfield): void {
            $e->select(DB::raw(1))->from(Sql::raw($elements))->whereRaw(Sql::trusted($enabled));
            $this->matchElement($e, $name, $property, $subfield->field->fallbackMode(), $clause, $chain);
        });
    }

    /**
     * COALESCE over the item's language rows along the chain.
     *
     * @param  list<int>  $chain
     */
    public function fallbackValue(Builder $q, string $table, string $key, string $fallbackMode, Comparison $comparison, array $chain): string
    {
        $languageIds = $fallbackMode === 'none' ? [$chain[0]] : $chain;
        $columns = [];

        foreach ($languageIds as $index => $languageId) {
            $alias = "l{$index}";
            $q->leftJoin("{$table} as {$alias}", function (JoinClause $join) use ($alias, $languageId): void {
                $join->on("{$alias}.page_id", '=', 's.page_id')->on("{$alias}.item", '=', 's.item')->where("{$alias}.language_id", '=', $languageId);
            });
            $columns[] = JsonSql::extract("{$alias}.data", $key, $comparison);
        }

        return count($columns) === 1 ? $columns[0] : 'COALESCE('.implode(', ', $columns).')';
    }

    /**
     * @param  list<int>  $chain
     */
    private function matchElement(Builder $e, string $name, ItemProperty $property, string $fallbackMode, Clause $clause, array $chain): void
    {
        if ($property->isList) {
            $this->assertListOperator($name, $clause);
            $e->where(function (Builder $any) use ($name, $clause): void {
                foreach ($clause->values as $value) {
                    JsonSql::elementContains($any, $name, $value, 'or');
                }
            });

            return;
        }

        if (! $property->isTranslatable) {
            $this->compare($e, JsonSql::elementValue($name, $property->comparison), $property->comparison, $clause);

            return;
        }

        $codes = [];

        foreach ($fallbackMode === 'none' ? [$chain[0]] : $chain as $languageId) {
            $code = $this->languages->find($languageId)?->code;

            if ($code !== null) {
                $codes[] = JsonSql::elementValue($name, $property->comparison, (string) $code);
            }
        }

        $this->compare($e, count($codes) === 1 ? $codes[0] : 'COALESCE('.implode(', ', $codes).')', $property->comparison, $clause);
    }

    private function compare(Builder $q, string $expression, Comparison $comparison, Clause $clause): void
    {
        $this->comparison->apply($q, Sql::raw($expression), $clause, ClauseComparison::caster($comparison), likeAllowed: $comparison === Comparison::Text);
    }

    private function assertListOperator(string $name, Clause $clause): void
    {
        if ($clause->operator !== '=') {
            throw new SelectorException("[{$name}] holds a list: use = or !=.");
        }
    }
}
