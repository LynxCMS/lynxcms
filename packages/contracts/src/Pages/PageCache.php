<?php

declare(strict_types=1);

namespace LynxCms\Contracts\Pages;

/**
 * What code that renders a page can tell the page cache (lynxcms/cache), without depending on it.
 * Without the cache module installed, nothing reads it.
 *
 * @api
 */
final class PageCache
{
    /**
     * Request attribute that keeps the page being rendered out of the cache when set to true, as
     * a template's @nocache does: request()->attributes->set(PageCache::SKIP, true).
     */
    public const string SKIP = 'lynx-cache.skip';
}
