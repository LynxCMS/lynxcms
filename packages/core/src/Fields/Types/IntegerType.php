<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/** Whole number. Shared across languages. */
final class IntegerType extends BaseFieldType implements DescribesPhpType
{
    public static function name(): string
    {
        return 'integer';
    }

    public static function description(): string
    {
        return 'Whole number, e.g. a price in cents, a duration or a count. Shared across languages.';
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->bigInteger('data')->nullable();
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'min' => $schema->integer()->description('Smallest allowed value.'),
            'max' => $schema->integer()->description('Largest allowed value.'),
        ];
    }

    public function settingsRules(): array
    {
        return ['min' => ['sometimes', 'integer'], 'max' => ['sometimes', 'integer']];
    }

    public function rules(array $settings): array
    {
        return array_values(array_filter([
            'nullable', 'integer',
            isset($settings['min']) ? 'min:'.Value::int($settings['min']) : null,
            isset($settings['max']) ? 'max:'.Value::int($settings['max']) : null,
        ]));
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        return $value === null || $value === '' ? null : (is_numeric($value) ? (int) $value : $value);
    }

    public function wake(array $rows, array $settings): mixed
    {
        return $rows === [] || $rows[0]->data === null ? null : Value::int($rows[0]->data);
    }

    public function comparison(): Comparison
    {
        return Comparison::Integer;
    }

    public function phpType(Field $field): string
    {
        return 'int|null';
    }
}
