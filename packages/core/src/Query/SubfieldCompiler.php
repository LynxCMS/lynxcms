<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\Items\HasItemProperties;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\ItemProperty;
use LynxCms\Core\Fields\Items\ItemTable;
use LynxCms\Core\Fields\Items\Subfield;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Models\Field;

/**
 * Selector clauses on item field subfields (ADR-0044): "credits.partner=7", "credits.role%=Real",
 * "blocks.type=video", and through ItemPropertyCompiler what items hold
 * besides subfields ("gallery.tags=hero", "blocks.gallery.count>0"). A page matches when one of
 * its enabled items matches; "!=" means none does. Translatable subfields match the value
 * visitors see, with fallback.
 * Part of the selector compiler, so it may build trusted SQL.
 *
 * @internal
 */
final readonly class SubfieldCompiler
{
    public function __construct(private FieldRegistry $fields, private ClauseComparison $comparison, private ItemPropertyCompiler $properties) {}

    /**
     * @param  list<int>  $chain  language ids to read translatable subfields in
     * @param  Closure(string): int  $pageId  a page id or path to its id
     */
    public function apply(Builder $query, Clause $clause, array $chain, Closure $pageId, string $pagesId): void
    {
        [$name, $sub] = explode('.', $clause->field, 2);
        $field = $this->fields->find($name) ?? throw new SelectorException("Unknown field [{$name}] in selector.");
        $type = $field->fieldType();

        if (! $type instanceof ItemFieldType) {
            throw new SelectorException("Field [{$name}] has no subfields; [{$clause->field}] cannot be used in selectors.");
        }

        $negate = $clause->operator === '!=';
        $positive = new Clause($clause->field, $negate ? '=' : $clause->operator, $clause->values);
        $table = $field->table();
        $matcher = $this->matcher($type, $field, $sub, $positive, $chain, $pageId);

        $query->{$negate ? 'whereNotExists' : 'whereExists'}(function (Builder $q) use ($table, $pagesId, $matcher): void {
            $q->select(DB::raw(1))->from("{$table} as s")->whereColumn('s.page_id', $pagesId)
                ->where('s.language_id', ItemTable::ITEM_ROW)->where('s.enabled', true);
            $matcher($q);
        });
    }

    /**
     * What one item row "s" must match: its type, a property the type declares, a subfield, or
     * something inside a nested subfield ("gallery.tags").
     *
     * @param  list<int>  $chain
     * @param  Closure(string): int  $pageId
     * @return Closure(Builder): void
     */
    private function matcher(ItemFieldType $type, Field $field, string $sub, Clause $clause, array $chain, Closure $pageId): Closure
    {
        $table = $field->table();
        $property = $type instanceof HasItemProperties ? ($type->itemProperties()[$sub] ?? null) : null;

        return match (true) {
            $sub === 'type' => fn (Builder $q) => $this->comparison->apply($q, 's.type', $clause, likeAllowed: true),
            $property instanceof ItemProperty => fn (Builder $q) => $this->properties->matchRow($q, $table, $sub, $property, $field->fallbackMode(), $clause, $chain),
            str_contains($sub, '.') => $this->nestedMatcher($type, $field, $sub, $clause, $chain),
            default => fn (Builder $q) => $this->match($q, $table, $this->subfield($type, $field, $sub), $clause, $chain, $pageId),
        };
    }

    /**
     * @param  list<int>  $chain
     * @return Closure(Builder): void
     */
    private function nestedMatcher(ItemFieldType $type, Field $field, string $sub, Clause $clause, array $chain): Closure
    {
        [$name, $property] = explode('.', $sub, 2);
        $subfield = $this->subfield($type, $field, $name);

        return fn (Builder $q) => $this->properties->matchNested($q, $subfield, $property, $clause, $chain);
    }

    /**
     * @param  list<int>  $chain
     * @param  Closure(string): int  $pageId
     */
    private function match(Builder $q, string $table, Subfield $subfield, Clause $clause, array $chain, Closure $pageId): void
    {
        $subType = $subfield->type();
        $comparison = $subType->comparison();

        if ($comparison === Comparison::None) {
            throw new SelectorException("Subfield [{$subfield->name()}] cannot be used in selectors.");
        }

        if ($subType->multiple()) {
            $this->contains($q, $subfield, $clause, $subType instanceof ReferencesPages ? $pageId : fn (string $v): int => (int) $v);

            return;
        }

        $expression = $subfield->isTranslatable()
            ? $this->properties->fallbackValue($q, $table, $subfield->name(), $subfield->field->fallbackMode(), $comparison, $chain)
            : JsonSql::extract('s.data', $subfield->name(), $comparison);

        $this->comparison->apply($q, Sql::raw($expression), $clause, ClauseComparison::caster($comparison), likeAllowed: $comparison === Comparison::Text);
    }

    /**
     * Lists (page references): one of the values is in the list.
     *
     * @param  Closure(string): int  $toInt
     */
    private function contains(Builder $q, Subfield $subfield, Clause $clause, Closure $toInt): void
    {
        if ($clause->operator !== '=') {
            throw new SelectorException("Subfield [{$subfield->name()}] holds a list: use = or !=.");
        }

        $q->where(function (Builder $any) use ($subfield, $clause, $toInt): void {
            foreach ($clause->values as $value) {
                JsonSql::contains($any, 's.data', $subfield->name(), $toInt($value), 'or');
            }
        });
    }

    private function subfield(ItemFieldType $type, Field $field, string $name): Subfield
    {
        foreach ($type->allSubfields($field->config()) as $subfield) {
            if ($subfield->name() === $name) {
                return $subfield;
            }
        }

        throw new SelectorException("Field [{$field->name}] has no subfield [{$name}].");
    }
}
