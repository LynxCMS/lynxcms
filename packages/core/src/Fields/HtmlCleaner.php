<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans HTML for html textarea fields on every save, whoever sends it (the editor, the API,
 * MCP, the agent, imports): an allowlist of text markup, links limited to safe schemes, and no
 * scripts, event handlers, styles or embeds. Rendering prints stored HTML as is, so this is the
 * only line of defence against stored cross-site scripting.
 *
 * @api
 */
final class HtmlCleaner
{
    /** Elements the rich text editor produces, with no attributes. */
    public const array ELEMENTS = ['p', 'br', 'hr', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 's', 'u', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre'];

    public const array LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    private ?HtmlSanitizer $sanitizer = null;

    public function clean(string $html): string
    {
        $clean = $this->sanitizer()->sanitize($html);

        // Links opening a new window get noopener. Safe on sanitized output: attribute values and
        // text there have their < > and quotes encoded, so a title cannot fake target="_blank".
        return preg_replace_callback(
            '/<a(\s[^>]*)>/',
            fn (array $match): string => preg_match('/\starget="_blank"/', $match[1]) === 1 ? '<a'.LinkRelSanitizer::withNoopener($match[1]).'>' : $match[0],
            $clean,
        ) ?? $clean;
    }

    private function sanitizer(): HtmlSanitizer
    {
        if ($this->sanitizer instanceof HtmlSanitizer) {
            return $this->sanitizer;
        }

        $config = new HtmlSanitizerConfig()
            ->allowElement('a', ['href', 'target', 'title', 'rel'])
            ->allowLinkSchemes(self::LINK_SCHEMES)
            ->allowRelativeLinks()
            // Only the relations editors may choose; noopener is added above for new windows.
            ->withAttributeSanitizer(new LinkRelSanitizer)
            // The default cuts input at 20,000 characters, which would truncate long articles.
            ->withMaxInputLength(-1);

        foreach (self::ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        return $this->sanitizer = new HtmlSanitizer($config);
    }
}
