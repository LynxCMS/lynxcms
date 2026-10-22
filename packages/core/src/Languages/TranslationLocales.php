<?php

declare(strict_types=1);

namespace LynxCms\Core\Languages;

/**
 * The locales Laravel's translator tries, widened for regional locales: a language set to
 * "pt-PT" reads lang/pt-PT, lang/pt_PT, then lang/pt (where packages and laravel-lang keep
 * Portuguese), then the app's fallback. Without it, a regional locale skips the language's own
 * translations and shows the fallback language.
 *
 * @internal
 */
final class TranslationLocales
{
    /**
     * @param  list<string>  $locales  the locale, then the fallback, as Laravel gives them
     * @return list<string>
     */
    public static function expand(array $locales): array
    {
        $expanded = [];

        foreach ($locales as $locale) {
            $expanded[] = $locale;
            $expanded[] = strtr($locale, ['-' => '_', '_' => '-']);
            $expanded[] = (string) preg_replace('/[-_].*$/', '', $locale);
        }

        return array_values(array_unique(array_filter($expanded, fn (string $locale): bool => $locale !== '')));
    }
}
