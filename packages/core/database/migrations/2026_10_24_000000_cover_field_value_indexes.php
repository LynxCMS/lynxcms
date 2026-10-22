<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Fields\FieldSchemaManager;
use LynxCms\Core\Models\Field;

/**
 * Field tables made before covering indexes get them (FieldSchemaManager::indexes): the value
 * index gains the page id, and on translatable fields it leads with the language, replacing the
 * index on the language alone. Tables without a value index (text areas, items) are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rebuild(fn (bool $translatable): array => FieldSchemaManager::indexes($translatable, true));
    }

    public function down(): void
    {
        $this->rebuild(fn (bool $translatable): array => $translatable
            ? ['_lang' => ['language_id'], '_data' => ['data']]
            : ['_data' => ['data']]);
    }

    /**
     * @param  Closure(bool): array<string, list<string>>  $indexes
     */
    private function rebuild(Closure $indexes): void
    {
        foreach (Field::query()->get() as $field) {
            $table = $field->table();
            $prefix = DB::getTablePrefix().$table;
            $existing = Schema::hasTable($table) ? array_column(Schema::getIndexes($table), 'columns', 'name') : [];

            if (! isset($existing["{$prefix}_data"])) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($existing, $prefix, $indexes, $table): void {
                foreach (['_data', '_lang'] as $suffix) {
                    if (isset($existing["{$prefix}{$suffix}"])) {
                        $blueprint->dropIndex("{$prefix}{$suffix}");
                    }
                }

                foreach ($indexes(Schema::hasColumn($table, 'language_id')) as $suffix => $columns) {
                    $blueprint->index($columns, "{$prefix}{$suffix}");
                }
            });
        }
    }
};
