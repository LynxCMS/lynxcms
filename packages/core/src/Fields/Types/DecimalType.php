<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * A number with decimals, such as a price or a rating. Shared across languages. Stored in a
 * fixed decimal column and rounded to the field's "places" on save, so changing "places" later
 * needs no table change. Templates read a float.
 */
final class DecimalType extends BaseFieldType implements DescribesPhpType
{
    private const int DEFAULT_PLACES = 2;

    public static function name(): string
    {
        return 'decimal';
    }

    public static function description(): string
    {
        return 'A number with decimals, e.g. a price or a rating. Setting "places" (default 2) rounds it. Shared across languages.';
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->decimal('data', 24, 8)->nullable();
    }

    public function comparison(): Comparison
    {
        return Comparison::Decimal;
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'places' => $schema->integer()->description('Digits after the decimal point, 0 to 8. Default 2.'),
            'min' => $schema->number()->description('Smallest allowed value.'),
            'max' => $schema->number()->description('Largest allowed value.'),
        ];
    }

    public function settingsRules(): array
    {
        return ['places' => ['sometimes', 'integer', 'min:0', 'max:8'], 'min' => ['sometimes', 'numeric'], 'max' => ['sometimes', 'numeric']];
    }

    public function rules(array $settings): array
    {
        return array_values(array_filter([
            'nullable', 'numeric',
            isset($settings['min']) ? 'min:'.Value::string($settings['min']) : null,
            isset($settings['max']) ? 'max:'.Value::string($settings['max']) : null,
        ]));
    }

    /** A number rounded to the field's places; "3,5" counts as 3.5. Text stays as is, so validation names it. */
    public function normalize(mixed $value, array $settings): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = is_string($value) ? str_replace(',', '.', trim($value)) : $value;

        return is_numeric($number) ? round((float) $number, Value::int($settings['places'] ?? self::DEFAULT_PLACES, self::DEFAULT_PLACES)) : $value;
    }

    public function wake(array $rows, array $settings): mixed
    {
        $data = $rows === [] ? null : $rows[0]->data;

        return is_numeric($data) ? (float) $data : null;
    }

    public function phpType(Field $field): string
    {
        return 'float|null';
    }
}
