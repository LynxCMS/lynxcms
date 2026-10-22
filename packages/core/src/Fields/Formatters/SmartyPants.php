<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Formatters;

use LynxCms\Contracts\Fields\FormatContext;

/**
 * Typographic quotes, dashes and ellipses in text, never inside tags, attributes, code or pre.
 */
final class SmartyPants extends TextFormatter
{
    private const array LITERAL = ['code', 'pre', 'kbd', 'script', 'style'];

    public static function name(): string
    {
        return 'smartypants';
    }

    public static function description(): string
    {
        return 'Curly quotes, en and em dashes and ellipses: "a" -- b... becomes “a” – b…';
    }

    public function format(string $html, FormatContext $context): string
    {
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $literal = 0;

        foreach ($parts as $index => $part) {
            if (str_starts_with($part, '<')) {
                $literal += $this->literalDepth($part);

                continue;
            }

            if ($literal === 0) {
                $parts[$index] = $this->smarten($part);
            }
        }

        return implode('', $parts);
    }

    /** +1 entering a literal element, -1 leaving it, 0 for any other tag. */
    private function literalDepth(string $tag): int
    {
        if (preg_match('/^<(\/?)([a-z0-9]+)/i', $tag, $match) !== 1 || ! in_array(strtolower($match[2]), self::LITERAL, true)) {
            return 0;
        }

        return $match[1] === '/' ? -1 : 1;
    }

    private function smarten(string $text): string
    {
        // Quotes arrive encoded: e() writes &quot; and &#039;, the HTML cleaner &#34; and &#39;.
        $text = str_replace(['&quot;', '&#34;', '&#x22;', '&#039;', '&#39;', '&#x27;'], ['"', '"', '"', "'", "'", "'"], $text);
        $text = str_replace(['---', '--', '...'], ['—', '–', '…'], $text);
        $text = (string) preg_replace('/(^|[\s(\[{\x{2014}\x{2013}])"/u', '$1“', $text);
        $text = str_replace('"', '”', $text);
        $text = (string) preg_replace("/(^|[\\s(\\[{\\x{2014}\\x{2013}])'/u", '$1‘', $text);

        return str_replace("'", '’', $text);
    }
}
