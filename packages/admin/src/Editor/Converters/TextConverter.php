<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor\Converters;

use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Core\Models\Field;

/**
 * Text and textarea: empty text becomes null, so the value falls back to another language (ADR-0005).
 *
 * @internal
 */
final class TextConverter implements FormConverter
{
    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
