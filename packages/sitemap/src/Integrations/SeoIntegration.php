<?php

declare(strict_types=1);

namespace LynxCms\Sitemap\Integrations;

use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Seo\RobotsTxt;
use LynxCms\Seo\SeoSchema;
use LynxCms\Sitemap\SitemapFilters;

/**
 * With lynxcms/seo: pages marked noindex, or whose canonical URL is another page, stay out of the
 * sitemap, and robots.txt points crawlers at it.
 *
 * @internal
 */
final class SeoIntegration
{
    public static function register(): void
    {
        app(SitemapFilters::class)->exclude(self::excludes(...));
        $addLine = fn (RobotsTxt $robots) => $robots->line(fn (): string => (bool) config('lynx-sitemap.enabled', true) ? 'Sitemap: '.url('/sitemap.xml') : '');

        if (app()->resolved(RobotsTxt::class)) {
            $addLine(app(RobotsTxt::class));
        } else {
            app()->afterResolving(RobotsTxt::class, $addLine);
        }
    }

    public static function excludes(Page $page, Language $language): bool
    {
        if ($page->templateField(SeoSchema::NOINDEX) !== null && Value::bool($page->getUnformatted(SeoSchema::NOINDEX))) {
            return true;
        }

        $canonical = $page->templateField(SeoSchema::CANONICAL) === null ? '' : Value::string($page->getUnformatted(SeoSchema::CANONICAL));

        return $canonical !== '' && self::absolute($canonical) !== self::absolute($page->localUrl($language));
    }

    private static function absolute(string $url): string
    {
        return preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) === 1 ? $url : rtrim(url('/'), '/').'/'.ltrim($url, '/');
    }
}
