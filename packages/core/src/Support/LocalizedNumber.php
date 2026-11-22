<?php

declare(strict_types=1);

namespace LynxCms\Core\Support;

use Illuminate\Support\Number;
use ValueError;

/**
 * Laravel's Number in the current locale. Number starts in English and does not follow the app's
 * locale. Intl refuses a locale it does not know, such as a language added with the code "jp";
 * those read as Number's own default.
 *
 * @internal
 */
final class LocalizedNumber
{
    public static function format(int|float $number): string
    {
        return self::inLocale(fn (): string => (string) Number::format($number));
    }

    public static function fileSize(int|float $bytes, int $precision = 0): string
    {
        return self::inLocale(fn (): string => Number::fileSize($bytes, $precision));
    }

    /**
     * @param  callable(): string  $format
     */
    private static function inLocale(callable $format): string
    {
        try {
            return Value::string(Number::withLocale(app()->getLocale(), $format));
        } catch (ValueError) {
            return $format();
        }
    }
}
