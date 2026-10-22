<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use LynxCms\Core\Languages\LanguageRegistry;

/**
 * The addresses a visitor can open a page at: its local URL in every active language the page is
 * live in and has a path in, each once. For modules that visit or export every page, such as the
 * page cache warmer and the static site export.
 *
 * @api
 */
final readonly class PageUrls
{
    public function __construct(private LanguageRegistry $languages) {}

    /** @return list<string> local URLs, e.g. ["/about", "/pt/sobre"] */
    public function of(Page $page): array
    {
        $urls = [];

        foreach ($this->languages->active() as $language) {
            if ($page->isActiveIn($language) && $page->path($language) !== null) {
                $urls[] = $page->localUrl($language);
            }
        }

        return array_values(array_unique($urls));
    }
}
