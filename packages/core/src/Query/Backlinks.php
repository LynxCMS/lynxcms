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

    /**
     * @return list<int> ids of the referencing pages, lowest first
     */
    public function pageIds(int $pageId): array
    {
        $queries = $this->fields->all()
            ->filter(fn (Field $field): bool => $field->fieldType() instanceof ReferencesPages)
            ->map(function (Field $field) use ($pageId): Builder {
                $query = DB::table($field->table())->select('page_id');
                $type = $field->fieldType();

                if ($type instanceof ReferencesPages) {
                    $type->whereReferences($query, $pageId, $field->config());
                }

                return $query;
            })
            ->values();

        $union = $queries->shift();

        if ($union === null) {
            return [];
        }

        foreach ($queries as $query) {
            $union->union($query);
        }

        $ids = Value::ints($union->pluck('page_id')->all());
        sort($ids);

        return array_values(array_unique($ids));
    }
}
