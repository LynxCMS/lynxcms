<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Concerns;

use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\PageUrl;
use LynxCms\Core\Pages\PathResolver;

/**
 * Page names per language, publication per language, status flags and URLs. Part of Page, kept
 * apart to keep that class small; it uses Page's names, status and language helpers.
 *
 * @internal
 */
trait HasNamesAndUrls
{
    /** The page name in the current language, falling back to the default language's name. */
    public function name(Language|string|null $language = null): string
    {
        $language = $language === null ? $this->context()->current() : $this->registry()->get($language);

        return $this->names[$language->id]['name'] ?? $this->names[$this->registry()->default()->id]['name'] ?? '';
    }

    public function setLanguageName(Language|string $language, string $name, ?bool $active = null): static
    {
        $language = $this->registry()->get($language);
        $status = $active === null ? ($this->names[$language->id]['status'] ?? 'published') : ($active ? 'published' : 'disabled');
        $this->names[$language->id] = ['name' => $name, 'status' => $status];
        $this->dirtyNames[$language->id] = true;

        return $this;
    }

    public function setLanguageStatus(Language|string $language, bool $active): static
    {
        $language = $this->registry()->get($language);
        $this->names[$language->id] = ['name' => $this->names[$language->id]['name'] ?? '', 'status' => $active ? 'published' : 'disabled'];
        $this->dirtyNames[$language->id] = true;

        return $this;
    }

    public function isActiveIn(Language|string $language): bool
    {
        $language = $this->registry()->get($language);

        return ($this->names[$language->id]['status'] ?? null) === 'published';
    }

    public function hasStatus(int $flag): bool
    {
        return ($this->status & $flag) === $flag;
    }

    public function setStatus(int $flag, bool $on = true): static
    {
        $this->status = $on ? $this->status | $flag : $this->status & ~$flag;
        $this->dirtyAttributes = true;

        return $this;
    }

    /** Path without language prefix, e.g. "cursos/navegando-entrega-8"; null if the page has no URL there. */
    public function path(Language|string|null $language = null): ?string
    {
        if ($this->id === null) {
            return null;
        }

        $language = $language === null ? $this->context()->current() : $this->registry()->get($language);

        return app(PathResolver::class)->pathFor($this->id, $language);
    }

    /** Relative URL in the current language, e.g. "/pt/cursos/navegando-entrega-8/". */
    public function url(): string
    {
        return $this->localUrl($this->context()->current());
    }

    /**
     * Relative URL in a given language. A page with no name there, or taken offline there, links
     * to its default-language URL instead, which visitors can open (ADR-0093).
     */
    public function localUrl(Language|string $language): string
    {
        $language = $this->registry()->get($language);
        $default = $this->registry()->default();
        $path = $language->id === $default->id || $this->isActiveIn($language) ? $this->path($language) : null;

        if ($path === null) {
            $language = $default;
            $path = $this->path($language) ?? '';
        }

        return PageUrl::of($language->url_prefix, $path);
    }
}
