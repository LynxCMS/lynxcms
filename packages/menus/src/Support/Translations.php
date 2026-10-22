<?php

declare(strict_types=1);

namespace LynxCms\Menus\Support;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Support\Value;

/**
 * Turns "text" or {"default": "text", "pt": "texto"} into a value per active language code,
 * with null for languages not given (they fall back at read time).
 *
 * @internal
 */
final class Translations
{
    /**
     * @return array<string, string|null>
     */
    public static function normalize(mixed $value, string $key): array
    {
        $codes = lynx()->languages->all()->pluck('code')->map(Value::string(...))->all();
        $given = is_array($value) ? $value : [lynx()->languages->default()->code => $value];
        $unknown = array_diff(array_map(strval(...), array_keys($given)), $codes);

        if ($unknown !== []) {
            throw ValidationException::withMessages([$key => 'Unknown language ['.implode(', ', $unknown).'].']);
        }

        $result = [];

        foreach ($codes as $code) {
            $text = trim(Value::string($given[$code] ?? ''));
            $result[$code] = $text === '' ? null : $text;
        }

        return $result;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function hasDefault(array $values): bool
    {
        return ($values[lynx()->languages->default()->code] ?? null) !== null;
    }

    /**
     * @return array<string, null>
     */
    public static function empty(): array
    {
        return array_fill_keys(lynx()->languages->all()->pluck('code')->map(Value::string(...))->all(), null);
    }
}
