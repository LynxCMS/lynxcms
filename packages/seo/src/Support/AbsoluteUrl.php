<?php

declare(strict_types=1);

namespace LynxCms\Seo\Support;

/**
 * Search engines and social networks need full URLs; Lynx builds relative ones.
 *
 * @internal
 */
final class AbsoluteUrl
{
    /** "/courses/" becomes "https://site.test/courses/"; full and protocol-relative URLs stay. */
    public static function of(string $url): string
    {
        if (preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $url) === 1) {
            return $url;
        }

        return rtrim(url('/'), '/').'/'.ltrim($url, '/');
    }
}
