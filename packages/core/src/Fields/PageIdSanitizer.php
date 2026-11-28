<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * The data-page of a link in an html field (ADR-0145): the id of the page it names, a whole
 * number, or nothing.
 *
 * @internal
 */
final class PageIdSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): array
    {
        return ['a'];
    }

    public function getSupportedAttributes(): array
    {
        return [PageLinks::ATTRIBUTE];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return preg_match('/^[1-9][0-9]{0,18}$/D', $value) === 1 ? $value : null;
    }
}
