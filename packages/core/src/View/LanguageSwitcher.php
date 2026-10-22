<?php

declare(strict_types=1);

namespace LynxCms\Core\View;

use Illuminate\Http\Request;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;

/**
 * Links to a page in each language it is published in, for a language switcher. The current
 * query string goes along (filters, sorting, a draft preview), so switching language keeps the
 * visitor where they were. The hreflang tags (HreflangBuilder) stay without it: search engines
 * should see one URL per language.
 *
 * @api
 */
final readonly class LanguageSwitcher
{
    public function __construct(private LanguageRegistry $languages, private LanguageContext $context) {}

    /**
     * @return list<array{language: Language, url: string, isCurrent: bool}>
     */
    public function links(Page $page, ?Request $request = null): array
    {
        $request ??= request();
        $query = http_build_query($request->query());
        $current = $this->context->current();
        $default = $this->languages->default();
        $links = [];

        foreach ($this->languages->active() as $language) {
            if ($language->id !== $default->id && (! $page->isActiveIn($language) || $page->path($language) === null)) {
                continue;
            }

            $links[] = [
                'language' => $language,
                'url' => $page->localUrl($language).($query === '' ? '' : '?'.$query),
                'isCurrent' => $language->id === $current->id,
            ];
        }

        return $links;
    }
}
