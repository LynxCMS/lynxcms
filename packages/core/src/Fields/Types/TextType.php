<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Fields\ProvidesSearchText;
use LynxCms\Core\Fields\Types\Concerns\PresentsSharedText;
use LynxCms\Core\Fields\Types\Concerns\ProvidesPlainSearchText;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\SharedValue;
use LynxCms\Core\Support\Value;

/** Single-line text, up to 255 characters. Translatable. */
final class TextType extends BaseFieldType implements DescribesPhpType, PresentsValue, ProvidesSearchText
{
    use PresentsSharedText;
    use ProvidesPlainSearchText;

    public static function name(): string
    {
        return 'text';
    }

    public static function description(): string
    {
        return 'Single-line text up to 255 characters, e.g. a title or a name. Can be translatable.';
    }

    public function supportsTranslation(): bool
    {
        return true;
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->string('data', 255)->nullable();
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'max_length' => $schema->integer()->description('Maximum length, up to 255.'),
            'placeholder' => $schema->string()->description('Hint shown in empty inputs.'),
        ];
    }

    public function settingsRules(): array
    {
        return ['max_length' => ['sometimes', 'integer', 'min:1', 'max:255'], 'placeholder' => ['sometimes', 'nullable', 'string']];
    }

    public function rules(array $settings): array
    {
        return ['nullable', 'string', 'max:'.Value::int($settings['max_length'] ?? 255, 255)];
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        if ($value === null) {
            return null;
        }

        return trim(preg_replace('/\s+/u', ' ', Value::string($value)) ?? '');
    }

    /** A SharedValue, or null when empty (PresentsSharedText). */
    public function phpType(Field $field): string
    {
        return '\\'.SharedValue::class.'|null';
    }
}
