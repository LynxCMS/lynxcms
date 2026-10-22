<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Fields\Items\ItemTable;
use LynxCms\Core\Fields\Items\StoresItems;
use LynxCms\Core\Indexes\TrigramIndexes;
use LynxCms\Core\Models\Field;

/**
 * Runtime DDL for field tables. Runs inside the action's transaction; on PostgreSQL and SQLite
 * a failed CreateField therefore leaves nothing behind (ADR-0004, ADR-0013).
 *
 * @internal
 */
final class FieldSchemaManager
{
    public function create(Field $field): void
    {
        $type = $field->fieldType();

        // Explicit index names ({table}_pk, _lang, _data) stay unique and inside PostgreSQL's
        // 63-character limit; CreateField caps field names so they always fit.
        $index = DB::getTablePrefix().$field->table();

        if ($type instanceof StoresItems) {
            Schema::create($field->table(), fn (Blueprint $table) => ItemTable::define($table, $index));

            return;
        }

        Schema::create($field->table(), function (Blueprint $table) use ($field, $type, $index): void {
            $table->unsignedBigInteger('page_id');
            $keys = ['page_id'];

            if ($field->isTranslatable()) {
                $table->unsignedBigInteger('language_id');
                $keys[] = 'language_id';
            }

            if ($type->multiple()) {
                $table->unsignedInteger('sort')->default(0);
                $keys[] = 'sort';
            }

            $type->columns($table, $field->config());
            $table->primary($keys, "{$index}_pk");

            foreach (self::indexes($field->isTranslatable(), $type->indexable()) as $suffix => $columns) {
                $table->index($columns, "{$index}{$suffix}");
            }
        });

        $trigram = app(TrigramIndexes::class);

        if ($trigram->isEnabled()) {
            $trigram->create($field);
        }
    }

    /**
     * The secondary indexes of a field table, by name suffix. Values are indexed with the page id
     * so selectors find pages from the index alone; translatable values per language first
     * (TranslatableMatch), which also serves lookups by language.
     *
     * @return array<string, list<string>>
     */
    public static function indexes(bool $translatable, bool $indexable): array
    {
        return match (true) {
            $indexable && $translatable => ['_data' => ['language_id', 'data', 'page_id']],
            $indexable => ['_data' => ['data', 'page_id']],
            $translatable => ['_lang' => ['language_id']],
            default => [],
        };
    }

    public function drop(Field $field): void
    {
        Schema::dropIfExists($field->table());
    }

    public function exists(Field $field): bool
    {
        return Schema::hasTable($field->table());
    }
}
