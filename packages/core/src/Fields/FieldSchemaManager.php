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
    /** The name a table takes while it is rebuilt: never a field's, whose tables all start lynx_f_. */
    private const string REBUILDING = 'lynx_rebuild_';

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

    /**
     * Rebuilds a shared field's table as a translatable one, the field already marked translatable:
     * each row becomes the given language's. The old table's indexes go first, since index names
     * are global on PostgreSQL and SQLite, and the new table gets its own under the same names.
     */
    public function makeTranslatable(Field $field, int $languageId): void
    {
        $table = $field->table();
        $old = self::REBUILDING.$field->id;
        $this->dropIndexes($field);
        Schema::rename($table, $old);
        $this->create($field);

        $columns = array_values(array_diff(Schema::getColumnListing($old), ['language_id']));
        DB::table($table)->insertUsing([...$columns, 'language_id'], DB::table($old)->select($columns)->selectRaw('? as language_id', [$languageId]));
        Schema::drop($old);
    }

    /** The indexes of a shared field's table, its primary key on PostgreSQL included. */
    private function dropIndexes(Field $field): void
    {
        $table = $field->table();
        $index = DB::getTablePrefix().$table;
        $suffixes = array_keys([...self::indexes(false, $field->fieldType()->indexable()), TrigramIndexes::SUFFIX => []]);
        $names = array_filter(array_map(fn (string $suffix): string => $index.$suffix, $suffixes), fn (string $name): bool => Schema::hasIndex($table, $name));

        Schema::table($table, function (Blueprint $blueprint) use ($names, $index): void {
            foreach ($names as $name) {
                $blueprint->dropIndex($name);
            }

            if (DB::getDriverName() === 'pgsql') {
                $blueprint->dropPrimary("{$index}_pk");
            }
        });
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
