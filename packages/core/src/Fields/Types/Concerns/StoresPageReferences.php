<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types\Concerns;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\Visibility\PageVisibility;
use LynxCms\Core\Support\Value;
use stdClass;

/**
 * Ordered page ids, one row each, shared across languages: how page references and page tables
 * store, compare and present their pages (ADR-0042).
 */
trait StoresPageReferences
{
    public function multiple(): bool
    {
        return true;
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->unsignedBigInteger('data');
    }

    /**
     * Whether the field holds several pages; page references can be limited to one.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function holdsSeveral(array $settings): bool
    {
        return (bool) ($settings['multiple'] ?? true);
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        if ($value === null) {
            return [];
        }

        $ids = [];

        foreach (is_iterable($value) ? $value : [$value] as $item) {
            $id = $item instanceof Page ? (int) $item->id : Value::int($item);

            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $this->holdsSeveral($settings) ? $ids : array_slice($ids, 0, 1);
    }

    public function sleep(mixed $value, array $settings): array
    {
        return array_map(fn (int $id): array => ['data' => $id], Value::ints($value));
    }

    public function wake(array $rows, array $settings): mixed
    {
        return array_map(fn (stdClass $row): int => Value::int($row->data), $rows);
    }

    public function comparison(): Comparison
    {
        return Comparison::Integer;
    }

    /** A PageArray, or for a field holding one page (multiple off) that page or null (ADR-0070). */
    public function present(mixed $value, Field $field): mixed
    {
        // Pages a visibility rule refuses are left out for the visitor (ADR-0062).
        $pages = app(PageVisibility::class)->only(app(PageLoader::class)->many(Value::ints((array) $value)));

        return $this->holdsSeveral($field->config()) ? $pages : $pages->first();
    }

    public function pageIds(mixed $value, array $settings): array
    {
        return Value::ints((array) $value);
    }

    public function mapPageIds(mixed $value, Closure $map, array $settings): mixed
    {
        $mapped = [];

        foreach (Value::array($value) as $reference) {
            $result = is_int($reference) || is_string($reference) ? $map($reference) : null;

            if ($result !== null) {
                $mapped[] = $result;
            }
        }

        return $mapped;
    }

    public function whereReferences(Builder $query, int $pageId, array $settings): void
    {
        $query->where('data', $pageId);
    }

    public function phpType(Field $field): string
    {
        return $this->holdsSeveral($field->config()) ? '\\'.PageArray::class : '\\'.Page::class.'|null';
    }
}
