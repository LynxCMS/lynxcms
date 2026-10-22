<?php

declare(strict_types=1);

namespace LynxCms\Core\View;

use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;

/**
 * Alternate-language links for a page: one per active language the page is published in, plus
 * x-default. Used by @hreflang and by any template engine plugin.
 *
 * @api
 */
final readonly class HreflangBuilder
{
    public function __construct(private LanguageRegistry $languages) {}

    /**
     * @return list<array{hreflang: string, href: string}>
     */
    public function links(Page $page): array
    {
        $links = [];
        $default = $this->languages->default();

        foreach ($this->languages->active() as $language) {
            if ($language->id !== $default->id && (! $page->isActiveIn($language) || $page->path($language) === null)) {
                continue;
            }

            $links[] = ['hreflang' => $this->tag($language), 'href' => $this->absolute($page->localUrl($language))];
        }

        $links[] = ['hreflang' => 'x-default', 'href' => $this->absolute($page->localUrl($default))];

        return $links;
    }

    public function html(Page $page): string
    {
        return implode("\n", array_map(
            fn (array $l): string => '<link rel="alternate" hreflang="'.e($l['hreflang']).'" href="'.e($l['href']).'">',
            $this->links($page),
        ));
    }

    private function absolute(string $path): string
    {
        return rtrim(url('/'), '/').$path;
    }

    private function tag(Language $language): string
    {
        return str_replace('_', '-', $language->appLocale());
    }
}
