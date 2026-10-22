<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor\Converters;

use DateTimeInterface;
use LynxCms\Admin\Editor\FormConverter;
use LynxCms\Admin\Editor\PreparesDisplay;
use LynxCms\Core\Models\Field;

/**
 * Dates: the value of a native date input ("2026-03-09") or date-time input ("2026-03-09T14:30")
 * in the form; the date type parses and validates what comes back.
 *
 * @internal
 */
final class DateConverter implements FormConverter, PreparesDisplay
{
    public function toForm(mixed $value, ?Field $field = null): mixed
    {
        if (! $value instanceof DateTimeInterface) {
            return is_string($value) ? $value : '';
        }

        return $value->format(self::hasTime($field) ? 'Y-m-d\TH:i' : 'Y-m-d');
    }

    public function fromForm(mixed $value, ?Field $field = null): mixed
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : $value;
    }

    public function display(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format($value->format('H:i') === '00:00' ? 'Y-m-d' : 'Y-m-d H:i') : $value;
    }

    public static function hasTime(?Field $field): bool
    {
        return ($field?->config()['time'] ?? false) === true;
    }
}
