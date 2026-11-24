<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Models\Field;

/**
 * A yes or no value, which the admin shows as one checkbox or, with "input": "switch", a switch.
 * Shared across languages.
 *
 * @internal
 */
final class ToggleType extends BaseFieldType implements DescribesPhpType
{
    /** How editors set it: one checkbox labeled Yes (default), or a switch. */
    public const array INPUTS = ['checkbox', 'switch'];

    public static function name(): string
    {
        return 'toggle';
    }

    public static function description(): string
    {
        return 'A yes or no value, e.g. "Is external course", shown to editors as one checkbox, or as a switch with "input": "switch". Shared across languages. For several options as checkboxes, use choice or page with "input": "checkboxes".';
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'input' => $schema->string()->enum(self::INPUTS)->description('How editors set it: checkbox (default) or switch.'),
        ];
    }

    public function settingsRules(): array
    {
        return [
            'input' => ['sometimes', 'in:'.implode(',', self::INPUTS)],
        ];
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->boolean('data')->nullable();
    }

    public function rules(array $settings): array
    {
        return ['nullable', 'boolean'];
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        return $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    public function isEmpty(mixed $value): bool
    {
        return $value === null;
    }

    public function wake(array $rows, array $settings): mixed
    {
        return $rows === [] || $rows[0]->data === null ? null : (bool) $rows[0]->data;
    }

    public function comparison(): Comparison
    {
        return Comparison::Boolean;
    }

    public function phpType(Field $field): string
    {
        return 'bool|null';
    }
}
