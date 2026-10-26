<?php

declare(strict_types=1);

namespace LynxCms\Core\Languages;

use Illuminate\Translation\MessageSelector;
use Override;

/**
 * Laravel's plural rules, looked up by the bare language: a language set to "pt-PT" or "fr-CA"
 * uses Portuguese or French plurals. Laravel's table lists regional locales with underscores
 * only (pt_PT), so the hyphenated locales Lynx languages use fell through to its default rule
 * and trans_choice('…', 3) picked the singular. Every regional locale in the table shares its
 * bare language's rule, so the bare language also covers regions the table leaves out.
 *
 * @internal
 */
final class RegionalMessageSelector extends MessageSelector
{
    /**
     * @param  string  $locale
     * @param  int|float  $number
     */
    #[Override]
    public function getPluralIndex($locale, $number): int
    {
        return parent::getPluralIndex((string) preg_replace('/[-_].*$/', '', $locale), $number);
    }
}
