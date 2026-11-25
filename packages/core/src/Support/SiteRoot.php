<?php

declare(strict_types=1);

namespace LynxCms\Core\Support;

/**
 * The site's own address (config app.url) without a trailing slash, for absolute URLs Lynx sends:
 * redirects and hreflang links. url('/') follows the request's Host header unless the app trusts
 * only its hosts, so a forged header could change where a cached 301 points.
 *
 * @internal
 */
final class SiteRoot
{
    public static function url(): string
    {
        return rtrim(Value::string(config('app.url')), '/');
    }
}
