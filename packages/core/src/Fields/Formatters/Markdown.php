<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Formatters;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use LynxCms\Contracts\Fields\FormatContext;

/**
 * Markdown to HTML at output, for fields whose format is plain text (gradual adoption; fields
 * with format "markdown" are already converted).
 */
final class Markdown extends TextFormatter
{
    public static function name(): string
    {
        return 'markdown';
    }

    public static function description(): string
    {
        return 'Markdown to HTML when the page is shown, for fields stored as plain text.';
    }

    public function producesHtml(): bool
    {
        return true;
    }

    public function format(string $html, FormatContext $context): string
    {
        return trim((string) new GithubFlavoredMarkdownConverter(['html_input' => 'allow', 'allow_unsafe_links' => false])->convert($html));
    }
}
