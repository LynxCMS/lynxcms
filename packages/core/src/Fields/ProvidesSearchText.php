<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields;

use LynxCms\Core\Models\Language;

/**
 * A field type whose values hold words to search (ADR-0054). The search index asks it for the
 * text of a value in each language; markup is stripped and accents folded by the index.
 *
 * @api
 */
interface ProvidesSearchText
{
    /**
     * The words of a value in $language. Translatable values arrive already read in that language,
     * with fallback; values that hold their own translations (items) pick them for $language.
     *
     * @param  array<string, mixed>  $settings
     */
    public function searchText(mixed $value, array $settings, Language $language): string;
}
