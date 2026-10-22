<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types\Concerns;

use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\SharedValue;

/**
 * Shared text fields format in templates like translatable ones do (ADR-0031); an empty one reads
 * as null, so @if, ?: and ?? see it as empty (ADR-0070).
 *
 * @internal
 */
trait PresentsSharedText
{
    public function present(mixed $value, Field $field): mixed
    {
        return $value === null || $value === '' ? null : new SharedValue($field, $value);
    }
}
