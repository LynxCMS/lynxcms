<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Fields;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Fields\Types\BaseFieldType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;
use LynxCms\PagePassword\StoredPassword;

/**
 * A page password: stored encrypted, read as a StoredPassword, never compared by selectors or
 * written to the search index. Read tools, history and content packages see only the
 * encrypted value.
 *
 * @api
 */
final class PasswordType extends BaseFieldType implements DescribesPhpType, PresentsValue
{
    private const int MAX_LENGTH = 200;

    public static function name(): string
    {
        return 'password';
    }

    public static function description(): string
    {
        return 'A password visitors type to open a page (lynxcms/page-password). Stored encrypted; empty means none.';
    }

    public function indexable(): bool
    {
        return false;
    }

    public function comparison(): Comparison
    {
        return Comparison::None;
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->text('data')->nullable();
    }

    public function rules(array $settings): array
    {
        return ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
            $value = Value::string($value);

            if (! StoredPassword::isStored($value) && mb_strlen(trim($value)) > self::MAX_LENGTH) {
                $fail('The password may have at most '.self::MAX_LENGTH.' characters.');
            }
        }];
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        $value = Value::string($value);

        if (StoredPassword::isStored($value)) {
            return $value;
        }

        $password = trim($value);

        return $password === '' ? null : StoredPassword::encrypt($password);
    }

    public function present(mixed $value, Field $field): StoredPassword
    {
        return new StoredPassword(is_string($value) ? $value : null);
    }

    public function phpType(Field $field): string
    {
        return '\\'.StoredPassword::class;
    }
}
