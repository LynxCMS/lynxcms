<?php

declare(strict_types=1);

namespace LynxCms\Core\Support;

use Stringable;

/**
 * Typed reads of loosely typed values (validated input, JSON settings, database rows).
 *
 * @internal
 */
final class Value
{
    public static function string(mixed $value, string $default = ''): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            $value instanceof Stringable => (string) $value,
            default => $default,
        };
    }

    public static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : self::string($value);
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return match (true) {
            is_int($value) => $value,
            is_bool($value) => (int) $value,
            is_float($value) => (int) $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => $default,
        };
    }

    public static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : self::int($value);
    }

    public static function bool(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * @return array<mixed>
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * A list's items, renumbered; anything else is an empty list.
     *
     * @return list<mixed>
     */
    public static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        $result = [];

        foreach (self::array($value) as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        return array_values(array_map(self::string(...), self::array($value)));
    }

    /**
     * @return list<int>
     */
    public static function ints(mixed $value): array
    {
        return array_values(array_map(self::int(...), self::array($value)));
    }
}
