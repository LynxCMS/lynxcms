<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types\Concerns;

use LynxCms\Core\Models\Language;

/**
 * Text values are their own search text (ADR-0054).
 */
trait ProvidesPlainSearchText
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function searchText(mixed $value, array $settings, Language $language): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
