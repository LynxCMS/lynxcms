<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

/**
 * A CSV cell a spreadsheet will show as text: a value (a visitor's answer, a page title) that starts like a formula
 * (=, +, -, @, a tab or a carriage return) gets a leading quote, so opening the export never runs
 * it (OWASP's advice for CSV injection). Plain numbers stay numbers.
 *
 * @api
 */
final class SpreadsheetCell
{
    private const array FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    public static function safe(string $value): string
    {
        if ($value === '' || is_numeric($value) || ! in_array($value[0], self::FORMULA_STARTS, true)) {
            return $value;
        }

        return "'".$value;
    }
}
