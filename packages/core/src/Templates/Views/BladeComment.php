<?php

declare(strict_types=1);

namespace LynxCms\Core\Templates\Views;

/**
 * Text from the schema (a template's or block's label) made safe to sit inside a Blade comment in
 * a view lynx:make:view writes. Labels are free text, so one holding "--}}" would end the comment
 * and turn the rest into Blade; braces, "@" and line breaks are dropped.
 *
 * @internal
 */
final class BladeComment
{
    public static function text(string $text): string
    {
        $plain = str_replace(['{', '}', '@'], '', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $plain));
    }
}
