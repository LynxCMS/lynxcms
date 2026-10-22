<?php

declare(strict_types=1);

namespace LynxCms\Seo\Support;

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Plain text from page fields, for tags that hold no markup: tags stripped, entities decoded,
 * whitespace collapsed, in the current language with fallback.
 *
 * @internal
 */
final class PageText
{
    private const string ELLIPSIS = '…';

    /** The field's text, or '' when the page is missing, lacks the field or holds no text. */
    public function of(?Page $page, string $field): string
    {
        if ($page === null || $page->templateField($field) === null) {
            return '';
        }

        $value = $page->getUnformatted($field);

        return is_scalar($value) ? $this->plain((string) $value) : '';
    }

    /** The first field of the list with text. */
    public function first(?Page $page, string ...$fields): string
    {
        foreach ($fields as $field) {
            $text = $this->of($page, $field);

            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    public function plain(string $html): string
    {
        // A space before block tags keeps "<p>one</p><p>two</p>" as two words; inline tags join.
        $spaced = Value::string(preg_replace('#<(/?(?:p|div|br|li|ul|ol|h[1-6]|tr|td|th|blockquote|section|article)\b)#i', ' <$1', $html));
        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(Value::string(preg_replace('/\s+/u', ' ', $text)));
    }

    /** At most $length characters, cut at a word, with an ellipsis when cut. */
    public function excerpt(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space === false ? $cut : mb_substr($cut, 0, $space), ' ,;:.-').self::ELLIPSIS;
    }
}
