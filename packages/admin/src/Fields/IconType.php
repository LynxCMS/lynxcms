<?php

declare(strict_types=1);

namespace LynxCms\Admin\Fields;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Admin\Icons\IconSet;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\Types\BaseFieldType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * An icon name from the admin's Lucide set, such as "calendar". Templates print it with
 * <x-dynamic-component :component="'lucide-'.$page->icon" />. The worked example of the custom
 * field types guide (docs/reference/field-types.md).
 *
 * @api
 */
final class IconType extends BaseFieldType implements DescribesPhpType
{
    private const int MAX_LENGTH = 64;

    public static function name(): string
    {
        return 'icon';
    }

    public static function description(): string
    {
        return 'An icon name from the Lucide set (https://lucide.dev/icons), such as "calendar" or "map-pin".';
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->string('data', self::MAX_LENGTH)->nullable();
    }

    public function rules(array $settings): array
    {
        return ['nullable', 'string', 'max:'.self::MAX_LENGTH, function (string $attribute, mixed $value, Closure $fail): void {
            if (! app(IconSet::class)->has(Value::string($value))) {
                $fail('Choose an icon from the list.');
            }
        }];
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        $name = trim(Value::string($value));

        return $name === '' ? null : $name;
    }

    public function phpType(Field $field): string
    {
        return 'string|null';
    }
}
