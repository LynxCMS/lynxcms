<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Fields\Types\BaseFieldType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Support\Value;
use stdClass;

/**
 * A module field type storing a note and some pages as JSON, to test the optional contracts: it
 * presents its own value object and declares the pages it references.
 */
final class SpotlightType extends BaseFieldType implements PresentsValue, ReferencesPages
{
    public static function name(): string
    {
        return 'spotlight';
    }

    public static function description(): string
    {
        return 'A note and the pages it points at.';
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->json('data')->nullable();
    }

    public function indexable(): bool
    {
        return false;
    }

    public function comparison(): Comparison
    {
        return Comparison::None;
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [];
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        $value = Value::array($value);

        return ['note' => Value::string($value['note'] ?? ''), 'pages' => Value::ints(Value::array($value['pages'] ?? []))];
    }

    public function sleep(mixed $value, array $settings): array
    {
        return [['data' => json_encode($value, JSON_THROW_ON_ERROR)]];
    }

    /** @param  list<stdClass>  $rows */
    public function wake(array $rows, array $settings): mixed
    {
        return $rows === [] ? null : Value::array(json_decode(Value::string($rows[0]->data), true));
    }

    public function present(mixed $value, Field $field): mixed
    {
        $value = Value::array($value);

        return (object) ['note' => Value::string($value['note'] ?? ''), 'pages' => app(PageLoader::class)->many(Value::ints(Value::array($value['pages'] ?? [])))];
    }

    public function pageIds(mixed $value, array $settings): array
    {
        return Value::ints(Value::array(Value::array($value)['pages'] ?? []));
    }

    public function mapPageIds(mixed $value, Closure $map, array $settings): mixed
    {
        $value = Value::array($value);
        $value['pages'] = array_values(array_filter(array_map($map, Value::array($value['pages'] ?? [])), fn (mixed $mapped): bool => $mapped !== null));

        return $value;
    }

    public function whereReferences(Builder $query, int $pageId, array $settings): void
    {
        // Text match on the stored JSON: whereJsonContains breaks on SQLite with a table prefix
        // (Laravel prefixes json_each), and a fixture needs no index.
        $query->where(fn (Builder $q) => $q->where('data', 'like', "%[{$pageId}]%")->orWhere('data', 'like', "%[{$pageId},%")
            ->orWhere('data', 'like', "%,{$pageId},%")->orWhere('data', 'like', "%,{$pageId}]%"));
    }
}
