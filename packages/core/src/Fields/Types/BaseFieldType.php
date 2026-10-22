<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Contracts\Fields\Comparison;
use LynxCms\Contracts\Fields\FieldType;

/**
 * Defaults for single-value field types stored in one "data" column.
 *
 * @api
 */
abstract class BaseFieldType implements FieldType
{
    public function supportsTranslation(): bool
    {
        return false;
    }

    public function multiple(): bool
    {
        return false;
    }

    public function indexable(): bool
    {
        return true;
    }

    public function comparison(): Comparison
    {
        return Comparison::Text;
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [];
    }

    public function settingsRules(): array
    {
        return [];
    }

    public function rules(array $settings): array
    {
        return [];
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        return $value;
    }

    public function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    public function sleep(mixed $value, array $settings): array
    {
        return [['data' => $value]];
    }

    public function wake(array $rows, array $settings): mixed
    {
        return $rows === [] ? null : $rows[0]->data;
    }

    public function isHtml(array $settings): bool
    {
        return false;
    }
}
