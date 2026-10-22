<?php

declare(strict_types=1);

namespace LynxCms\Core\Languages;

use Illuminate\Support\Collection;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Models\Setting;
use LynxCms\Core\Support\Flushable;

/**
 * Read access to languages, cached per request and flushed after every action.
 *
 * @api
 */
final class LanguageRegistry implements Flushable
{
    public const string DEFAULT_SETTING = 'default_language_id';

    /** @var Collection<int, Language>|null */
    private ?Collection $languages = null;

    private ?int $defaultId = null;

    /**
     * @return Collection<int, Language>
     */
    public function all(): Collection
    {
        return $this->languages ??= Language::query()->orderBy('sort')->orderBy('id')->get()->keyBy('id');
    }

    /**
     * @return Collection<int, Language>
     */
    public function active(): Collection
    {
        return $this->all()->filter(fn (Language $l): bool => $l->is_active);
    }

    public function find(Language|int|string|null $reference): ?Language
    {
        if ($reference === null) {
            return null;
        }

        if ($reference instanceof Language) {
            return $this->all()->get($reference->id);
        }

        if (is_int($reference) || ctype_digit($reference)) {
            return $this->all()->get((int) $reference);
        }

        return $this->all()->first(
            fn (Language $l): bool => $l->code === $reference || $l->uuid === $reference,
        );
    }

    public function get(Language|int|string $reference): Language
    {
        return $this->find($reference) ?? throw LanguageNotFound::for($reference);
    }

    public function byPrefix(string $prefix): ?Language
    {
        return $this->all()->first(fn (Language $l): bool => $l->url_prefix === $prefix);
    }

    public function hasDefault(): bool
    {
        return $this->all()->isNotEmpty();
    }

    public function default(): Language
    {
        $this->defaultId ??= (int) Setting::read(self::DEFAULT_SETTING, '0');

        return $this->all()->get($this->defaultId)
            ?? $this->all()->first()
            ?? throw new LanguageNotFound('Lynx has no languages yet. Run php artisan lynx:install.');
    }

    /**
     * The fallback chain for a language: itself, its fallbacks in order, ending with the default
     * language (ADR-0005). Cycles are cut; every language appears once.
     *
     * @return list<Language>
     */
    public function chain(Language|int|string $language): array
    {
        $current = $this->get($language);
        $chain = [];
        $seen = [];

        while ($current !== null && ! isset($seen[$current->id])) {
            $chain[] = $current;
            $seen[$current->id] = true;
            $current = $this->find($current->fallback_id);
        }

        $default = $this->default();

        if (! isset($seen[$default->id])) {
            $chain[] = $default;
        }

        return $chain;
    }

    /**
     * @return list<int>
     */
    public function chainIds(Language|int|string $language): array
    {
        return array_map(fn (Language $l): int => $l->id, $this->chain($language));
    }

    public function flush(): void
    {
        $this->languages = null;
        $this->defaultId = null;
    }
}
