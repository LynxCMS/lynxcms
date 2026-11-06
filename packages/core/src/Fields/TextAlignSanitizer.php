<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps one declaration of a block's style attribute, its text alignment, written the one way the
 * cleaner allows ("text-align: center"); every other declaration, and any other value, goes.
 *
 * @internal
 */
final class TextAlignSanitizer implements AttributeSanitizerInterface
{
    /** Blocks the editor can align. */
    public const array ELEMENTS = ['p', 'h2', 'h3', 'h4'];

    private const array ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    public function getSupportedElements(): array
    {
        return self::ELEMENTS;
    }

    public function getSupportedAttributes(): array
    {
        return ['style'];
    }

    /** "text-align: center", or null to drop the attribute. */
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        foreach (explode(';', $value) as $declaration) {
            [$property, $alignment] = array_map(trim(...), explode(':', $declaration, 2) + [1 => '']);

            if (strtolower($property) === 'text-align' && in_array(strtolower($alignment), self::ALIGNMENTS, true)) {
                return 'text-align: '.strtolower($alignment);
            }
        }

        return null;
    }
}
