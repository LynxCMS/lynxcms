<?php

declare(strict_types=1);

namespace LynxCms\Sitemap;

use Closure;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;

/**
 * Rules that keep a page out of the sitemap in a language, on top of status and templates.
 * Modules add theirs in a service provider:
 * app(SitemapFilters::class)->exclude(fn (Page $page, Language $language): bool => ...).
 *
 * @api
 */
final class SitemapFilters
{
    /** @var list<Closure(Page, Language): bool> */
    private array $filters = [];

    /** @param  Closure(Page, Language): bool  $filter  true leaves the page out in that language */
    public function exclude(Closure $filter): void
    {
        $this->filters[] = $filter;
    }

    public function excludes(Page $page, Language $language): bool
    {
        return array_any($this->filters, fn (Closure $filter) => $filter($page, $language));
    }
}
