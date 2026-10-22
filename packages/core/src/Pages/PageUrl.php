<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

/**
 * Builds a page's relative URL from its language prefix and path. URLs have no trailing slash,
 * as Laravel writes them, unless "lynx.trailing_slash" is true, as ProcessWire writes them
 * (ADR-0065). The home page is always "/". Both forms resolve to the page either way.
 *
 * @api
 */
final class PageUrl
{
    public static function of(string $prefix, string $path): string
    {
        $segments = array_filter([$prefix, trim($path, '/')], fn (string $segment): bool => $segment !== '');

        if ($segments === []) {
            return '/';
        }

        return '/'.implode('/', $segments).(config('lynx.trailing_slash', false) === true ? '/' : '');
    }

    /** A URL one level below $parentUrl, for previews of pages not saved yet. */
    public static function child(string $parentUrl, string $name): string
    {
        $parent = trim($parentUrl, '/');

        return self::of('', ($parent === '' ? '' : $parent.'/').$name);
    }
}
