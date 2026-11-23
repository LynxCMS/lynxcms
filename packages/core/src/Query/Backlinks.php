<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * Which pages reference a page in any field whose type references pages (ADR-0042): one union query over those fields' tables.
 *
 * @internal
 */
final readonly class Backlinks
{
    public function __construct(private FieldRegistry $fields) {}

    /** Targets per query: every one adds a condition, and SQLite caps how deep they nest. */
    private const int CHUNK = 100;

    /**
     * @return list<int> ids of the referencing pages, lowest first
     */
    public function pageIds(int $pageId): array
    {
        return $this->referencing([$pageId]);
    }

    /**
     * Pages that reference any of $targets, whatever their status, the targets themselves included.
     *
     * @param  list<int>  $targets
     * @return list<int> ids of the referencing pages, lowest first
     */
    public function referencing(array $targets): array
    {
        $ids = [];

        foreach (array_chunk(array_values(array_unique($targets)), self::CHUNK) as $chunk) {
            $union = $this->union($chunk);
            array_push($ids, ...($union === null ? [] : Value::ints($union->pluck('page_id')->all())));
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * One query per field that references pages, joined in a union.
     *
     * @param  list<int>  $targets
     */
    private function union(array $targets): ?Builder
    {
        $queries = $this->fields->all()
            ->filter(fn (Field $field): bool => $field->fieldType() instanceof ReferencesPages)
            ->map(fn (Field $field): Builder => $this->query($field, $targets))
            ->values();

        $union = $queries->shift();

        foreach ($queries as $query) {
            $union?->union($query);
        }

        return $union;
    }

    /**
     * @param  list<int>  $targets
     */
    private function query(Field $field, array $targets): Builder
    {
        $query = DB::table($field->table())->select('page_id');
        $type = $field->fieldType();

        if ($type instanceof ReferencesPages) {
            $query->where(function (Builder $any) use ($type, $targets, $field): void {
                foreach ($targets as $target) {
                    $any->orWhere(fn (Builder $one) => $type->whereReferences($one, $target, $field->config()));
                }
            });
        }

        return $query;
    }
}
