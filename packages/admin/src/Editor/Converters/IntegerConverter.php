<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor\Converters;

use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Core\Models\Field;

/**
 * Integers: input that is not a number stays a string, so pages.save reports it.
 *
 * @internal
 */
final class IntegerConverter implements FormConverter
{
    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        return is_int($value) ? (string) $value : '';
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return match (true) {
            $value === '' || $value === null => null,
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => $value,
        };
    }
}
