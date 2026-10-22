<?php

declare(strict_types=1);

namespace LynxCms\Core\Languages;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use LynxCms\Core\Models\Language;

/**
 * The language of the current request (or of the current closure). Bound as scoped,
 * so it is safe under Octane.
 *
 * @api
 */
final class LanguageContext
{
    private ?int $currentId = null;

    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly Application $app,
    ) {}

    public function current(): Language
    {
        return $this->languages->find($this->currentId) ?? $this->languages->default();
    }

    public function set(Language|int|string $language): Language
    {
        $language = $this->languages->get($language);
        $this->currentId = $language->id;
        $this->app->setLocale($language->appLocale());

        return $language;
    }

    public function isSet(): bool
    {
        return $this->currentId !== null;
    }

    /**
     * @return list<Language>
     */
    public function chain(): array
    {
        return $this->languages->chain($this->current());
    }

    /**
     * @return list<int>
     */
    public function chainIds(): array
    {
        return $this->languages->chainIds($this->current());
    }

    /**
     * Run a callback in another language, then restore the previous one.
     *
     * @template T
     *
     * @param  Closure(Language): T  $callback
     * @return T
     */
    public function with(Language|int|string $language, Closure $callback): mixed
    {
        $previousId = $this->currentId;
        $previousLocale = $this->app->getLocale();

        try {
            return $callback($this->set($language));
        } finally {
            $this->currentId = $previousId;
            $this->app->setLocale($previousLocale);
        }
    }
}
