<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * The rel of a link in an html field, by the LinkRel rule: only the relations editors may choose
 * are kept, and links that open a new window get noopener (withNoopener).
 *
 * @internal
 */
final class LinkRelSanitizer implements AttributeSanitizerInterface
{
    public const array CHOICES = LinkRel::CHOICES;

    public function getSupportedElements(): array
    {
        return ['a'];
    }

    public function getSupportedAttributes(): array
    {
        return ['rel'];
    }

    /** The chosen relations, or null to drop an attribute left empty. */
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return LinkRel::for($value, opensNewWindow: false);
    }

    /**
     * The attributes of an <a> tag with noopener added to its rel (or a rel="noopener" added).
     * Expects sanitized HTML, where quotes inside attribute values are encoded.
     */
    public static function withNoopener(string $attributes): string
    {
        $count = 0;
        $added = preg_replace_callback('/(^|\s)rel="([^"]*)"/', function (array $match): string {
            $tokens = preg_split('/\s+/', trim($match[2]), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return $match[1].'rel="'.implode(' ', in_array(LinkRel::NOOPENER, $tokens, true) ? $tokens : [...$tokens, LinkRel::NOOPENER]).'"';
        }, $attributes, 1, $count);

        return $count > 0 && $added !== null ? $added : rtrim($attributes).' rel="'.LinkRel::NOOPENER.'"';
    }
}
