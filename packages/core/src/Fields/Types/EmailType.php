<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Database\Schema\Blueprint;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\ProvidesSearchText;
use LynxCms\Core\Fields\Types\Concerns\ProvidesPlainSearchText;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/** An email address, trimmed with its domain in lower case. Shared across languages. */
final class EmailType extends BaseFieldType implements DescribesPhpType, ProvidesSearchText
{
    use ProvidesPlainSearchText;

    private const int MAX_LENGTH = 255;

    public static function name(): string
    {
        return 'email';
    }

    public static function description(): string
    {
        return 'An email address, e.g. a contact. Checked on save; the domain is stored in lower case. Shared across languages.';
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->string('data', self::MAX_LENGTH)->nullable();
    }

    public function rules(array $settings): array
    {
        return ['nullable', 'string', 'max:'.self::MAX_LENGTH, 'email:rfc'];
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        $email = trim(Value::string($value));

        if ($email === '') {
            return null;
        }

        // The local part may be case sensitive; the domain never is.
        $at = strrpos($email, '@');

        return $at === false ? $email : substr($email, 0, $at).mb_strtolower(substr($email, $at));
    }

    public function phpType(Field $field): string
    {
        return 'string|null';
    }
}
