<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Choices;

use LynxCms\Core\Support\LocalizedText;
use Stringable;

/**
 * One chosen option of a choice field: its stable value and its label in the current language.
 * Printing it prints the label: {{ $page->color }}.
 *
 * @api
 */
final readonly class Choice implements Stringable
{
    public function __construct(public string $value, private LocalizedText $labels) {}

    /** The label in the current language, with fallback; or in $language. */
    public function label(?string $language = null): string
    {
        return $this->labels->in($language);
    }

    public function __toString(): string
    {
        return $this->label();
    }
}
