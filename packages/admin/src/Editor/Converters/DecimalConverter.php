<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor\Converters;

use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Core\Models\Field;

/**
 * Decimals: the input's text goes back as typed ("3,25" included); the decimal type rounds it
 * and validation names text that is not a number.
 *
 * @internal
 */
final class DecimalConverter implements FormConverter
{
    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        return is_int($value) || is_float($value) ? rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.') : '';
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : $value;
    }
}
