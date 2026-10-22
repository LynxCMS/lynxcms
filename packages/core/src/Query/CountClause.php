<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\Items\ItemFieldType;
use LynxCms\Core\Fields\Items\ItemTable;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Tables;

/**
 * "{name}.count" clauses (ADR-0060): the number of child pages ("children.count>0", drafts and
 * system pages left out), of items in a repeater, matrix, images or files field (switched on
 * items only), or of values in a shared field holding several (page references, choices).
 *
 * @internal
 */
final readonly class CountClause
{
    public const string SUFFIX = '.count';

    public function __construct(private FieldRegistry $fields, private ClauseComparison $comparison) {}

    /** Whether the clause counts something: one name followed by ".count". */
    public static function matches(Clause $clause): bool
    {
        return str_ends_with($clause->field, self::SUFFIX) && substr_count($clause->field, '.') === 1;
    }

    public function apply(Builder $query, Clause $clause, string $pagesId): void
    {
        $name = substr($clause->field, 0, -strlen(self::SUFFIX));
        $count = $name === 'children' ? $this->children($pagesId) : $this->values($name, $pagesId);

        $this->comparison->apply($query, Sql::raw("({$count})"), $clause, ClauseComparison::caster(Comparison::Integer), likeAllowed: false);
    }

    private function children(string $pagesId): string
    {
        $grammar = DB::connection()->getQueryGrammar();
        $column = fn (string $name): string => $grammar->wrap("c.{$name}");

        return 'SELECT COUNT(*) FROM '.$grammar->wrapTable(Tables::PAGES.' as c')
            .' WHERE '.$column('parent_id').' = '.$grammar->wrap($pagesId)
            .' AND '.$column('draft_of').' IS NULL AND ('.$column('status').' & '.PageStatus::SYSTEM.') = 0';
    }

    private function values(string $name, string $pagesId): string
    {
        $field = $this->fields->find($name) ?? throw new SelectorException("Unknown field [{$name}] in selector.");
        $grammar = DB::connection()->getQueryGrammar();
        $table = $grammar->wrapTable($field->table());
        $count = "SELECT COUNT(*) FROM {$table} WHERE {$table}.".$grammar->wrap('page_id').' = '.$grammar->wrap($pagesId);

        if ($field->fieldType() instanceof ItemFieldType) {
            return $count." AND {$table}.".$grammar->wrap('language_id').' = '.ItemTable::ITEM_ROW." AND {$table}.".$grammar->wrap('enabled').' = true';
        }

        if (! $this->isCountable($field)) {
            throw new SelectorException("Field [{$name}] holds one value; [{$name}.count] cannot be used in selectors.");
        }

        return $count;
    }

    private function isCountable(Field $field): bool
    {
        return $field->fieldType()->multiple() && ! $field->isTranslatable();
    }
}
