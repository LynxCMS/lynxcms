<?php

declare(strict_types=1);

namespace LynxCms\Forms\Fields;

use Illuminate\Database\Schema\Blueprint;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Core\Fields\ChoiceOptions;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\Types\BaseFieldType;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * Choices for a form field (ADR-0053): rows of a stable value and a label per language, so a
 * label can be reworded or translated without changing what submissions store. A value left
 * empty is made from the default-language label. Shared across languages; the labels hold them.
 *
 * @api
 */
final class OptionsType extends BaseFieldType implements DescribesPhpType
{
    public static function name(): string
    {
        return 'options';
    }

    public static function description(): string
    {
        return 'Choices for a select, radio or checkbox list: [{"value": "film", "label": {"pt": "Filme", "en": "Film"}}]. A value left empty is made from the default-language label.';
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
        $table->json('data')->nullable();
    }

    public function rules(array $settings): array
    {
        return ['nullable', 'array'];
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        $rows = ChoiceOptions::normalize($value);

        return $rows === [] ? null : $rows;
    }

    public function sleep(mixed $value, array $settings): array
    {
        return [['data' => $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]];
    }

    public function wake(array $rows, array $settings): mixed
    {
        return $rows === [] || $rows[0]->data === null ? null : json_decode(Value::string($rows[0]->data), true, 512, JSON_THROW_ON_ERROR);
    }

    public function phpType(Field $field): string
    {
        return 'list<array<string, mixed>>|null';
    }
}
