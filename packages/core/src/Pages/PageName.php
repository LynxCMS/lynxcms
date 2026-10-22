<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Str;

/**
 * The rule for URL names: lowercase ASCII letters, digits, "-" and "_". pages.save applies it to
 * names and to titles it derives names from; editors use it to preview the URL a page will get.
 *
 * @api
 */
final class PageName
{
    public const int MAX_LENGTH = 128;

    public static function from(string $text): string
    {
        $name = Str::lower(Str::ascii($text));
        $name = (string) preg_replace('/[^a-z0-9_-]+/', '-', $name);

        return Str::limit(trim((string) preg_replace('/-{2,}/', '-', $name), '-_'), self::MAX_LENGTH, '');
    }
}
