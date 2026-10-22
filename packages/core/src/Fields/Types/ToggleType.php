<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Models\Field;

/** On/off switch. Shared across languages. */
final class ToggleType extends BaseFieldType implements DescribesPhpType
{
    public static function name(): string
    {
        return 'toggle';
    }

    public static function description(): string
    {
        return 'On/off switch, e.g. "Is external course". Shared across languages.';
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
