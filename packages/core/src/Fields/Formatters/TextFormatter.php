<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Formatters;

use LynxCms\Contracts\Fields\Formatter;

/**
 * Base for the core formatters, which apply to text and textarea fields.
 *
 * @internal
 */
abstract class TextFormatter implements Formatter
{
    public function appliesTo(string $fieldType): bool
    {
        return in_array($fieldType, ['text', 'textarea'], true);
    }

    public function producesHtml(): bool
    {
        return false;
    }
}
