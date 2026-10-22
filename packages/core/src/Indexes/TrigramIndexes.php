<?php

declare(strict_types=1);

namespace LynxCms\Core\Indexes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\Items\StoresItems;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Query\Sql;

/**
 * Trigram indexes on PostgreSQL (ADR-0061): a GIN index on lower(data) for each text field, which
 * serves the contains operators (%=, *=, ~=, ^=, $=) as selectors write them.
 *
 * @internal
 */
final readonly class TrigramIndexes
{
    public const string SUFFIX = '_trgm';

    public function __construct(private FieldRegistry $fields) {}

    /** Whether new text fields get the index: lynx.trigram is on and the database is PostgreSQL. */
    public function isEnabled(): bool
    {
        return config('lynx.trigram') === true && $this->isSupported();
    }

    public function isSupported(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /** Installs pg_trgm and indexes every text field. Returns how many fields are indexed. */
    public function install(): int
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $count = 0;

        foreach ($this->fields->all() as $field) {
            if ($this->create($field)) {
                $count++;
            }
        }

        return $count;
    }

    /** Adds the index to a text field's table, unless it has one. Returns whether the field has it. */
    public function create(Field $field): bool
    {
        if (! $this->isIndexable($field)) {
            return false;
        }

        $grammar = DB::connection()->getQueryGrammar();
        $name = $grammar->wrap(DB::getTablePrefix().$field->table().self::SUFFIX);

        DB::statement(Sql::trusted("CREATE INDEX IF NOT EXISTS {$name} ON ".$grammar->wrapTable($field->table())
            .' USING gin (lower('.$grammar->wrap('data').') gin_trgm_ops)'));

        return true;
    }

    private function isIndexable(Field $field): bool
    {
        $type = $field->fieldType();

        return $type->comparison() === Comparison::Text && ! $type instanceof StoresItems && Schema::hasColumn($field->table(), 'data');
    }
}
