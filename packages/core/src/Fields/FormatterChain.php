<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use LynxCms\Contracts\Fields\FormatContext;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Value;

/**
 * A field value as HTML for templates (ADR-0031): first the conversion of the field's "format"
 * (plain text escaped, Markdown converted, HTML as stored), then the field's formatters in order,
 * then the HTML cleaner when a formatter may have added markup. Results are memoized per request.
 *
 * @internal
 */
final class FormatterChain implements Flushable
{
    /** @var array<string, string> */
    private array $memo = [];

    public function __construct(private readonly FormatterRegistry $formatters, private readonly HtmlCleaner $cleaner) {}

    public function render(Field $field, mixed $value, ?Language $language = null, ?int $pageId = null): string
    {
        $text = Value::string($value);
        $names = Value::strings($field->config()['formatters'] ?? []);
        $key = $field->name.'|'.($language->code ?? '').'|'.md5($text);

        return $this->memo[$key] ??= $this->format($this->base($field, $text), $names, new FormatContext($field->name, $field->type, $language?->code, $pageId));
    }

    public function flush(): void
    {
        $this->memo = [];
    }

    /** The conversion every value gets, decided by the field's "format". */
    private function base(Field $field, string $text): string
    {
        return match ($field->config()['format'] ?? 'plain') {
            'html' => $text,
            'markdown' => trim((string) new GithubFlavoredMarkdownConverter(['html_input' => 'strip', 'allow_unsafe_links' => false])->convert($text)),
            default => e($text),
        };
    }

    /**
     * @param  list<string>  $names
     */
    private function format(string $html, array $names, FormatContext $context): string
    {
        $addsMarkup = false;

        foreach ($names as $name) {
            if (! $this->formatters->has($name)) {
                continue; // a formatter whose module was removed: the value still renders
            }

            $formatter = $this->formatters->get($name);
            $html = $formatter->format($html, $context);
            $addsMarkup = $addsMarkup || $formatter->producesHtml();
        }

        return $addsMarkup ? $this->cleaner->clean($html) : $html;
    }
}
