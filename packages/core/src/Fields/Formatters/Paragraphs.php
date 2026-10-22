<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Formatters;

use LynxCms\Contracts\Fields\FormatContext;

/**
 * Plain text to paragraphs: a blank line starts a paragraph, a single line break becomes <br>.
 */
final class Paragraphs extends TextFormatter
{
    public static function name(): string
    {
        return 'paragraphs';
    }

    public static function description(): string
    {
        return 'Plain text to paragraphs: a blank line starts a new paragraph, a line break becomes a <br>.';
    }

    public function producesHtml(): bool
    {
        return true;
    }

    public function format(string $html, FormatContext $context): string
    {
        $blocks = preg_split('/\n\s*\n/', trim(str_replace(["\r\n", "\r"], "\n", $html))) ?: [];
        $paragraphs = array_filter(array_map(trim(...), $blocks), fn (string $block): bool => $block !== '');

        return implode('', array_map(fn (string $block): string => '<p>'.str_replace("\n", '<br>', $block).'</p>', $paragraphs));
    }
}
