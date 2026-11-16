<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

use LynxCms\Core\Models\Language;

/**
 * The plural reducers by locale (ADR-0119). A language finds its reducer by its locale
 * (Language::appLocale()), the full locale first (pt-BR), then the bare language (pt), so regional
 * variants (pt-BR, en-US, en-GB) use their language's rules unless one is registered. Register a
 * reducer in a service provider to add a language or replace a built-in one:
 * app(WordReducers::class)->register('nl', new DutchPlurals).
 *
 * @api
 */
final class WordReducers
{
    /** @var array<string, WordReducer> */
    private array $reducers = [];

    public function register(string $locale, WordReducer $reducer): void
    {
        $this->reducers[$this->normalize($locale)] = $reducer;
    }

    /** The language's reducer, or null when it has none or lynx.search.reduce_plurals is off. */
    public function for(Language $language): ?WordReducer
    {
        if (! (bool) config('lynx.search.reduce_plurals', true)) {
            return null;
        }

        $locale = $this->normalize($language->appLocale());

        return $this->reducers[$locale] ?? $this->reducers[explode('-', $locale)[0]] ?? null;
    }

    private function normalize(string $locale): string
    {
        return strtolower(str_replace('_', '-', $locale));
    }
}
