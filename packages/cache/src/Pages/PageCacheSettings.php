<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use LynxCms\Core\Support\Value;

/**
 * The page cache settings shared by the middleware, the pre-boot server and the web server rules.
 *
 * @internal
 */
final class PageCacheSettings
{
    /** @return list<string> lower case; the host of app.url when none are set */
    public function hosts(): array
    {
        $hosts = Value::strings(config('lynx-cache.pages.hosts'));

        if ($hosts === []) {
            $hosts = [Value::string(parse_url(Value::string(config('app.url')), PHP_URL_HOST))];
        }

        return array_values(array_map(strtolower(...), $hosts));
    }

    /** @return list<string> */
    public function bypassCookies(): array
    {
        return Value::strings(config('lynx-cache.pages.bypass_cookies'));
    }

    public function path(): string
    {
        return rtrim(Value::string(config('lynx-cache.pages.path')), '/');
    }

    /** Seconds edge caches may keep a stored page; 0 when they may not. */
    public function edgeTtl(): int
    {
        return Value::int(config('lynx-cache.pages.edge_ttl'));
    }

    /** Where `lynx:page-cache --compile` writes the settings serve.php reads. */
    public function compiledFile(): string
    {
        return Value::string(config('lynx-cache.pages.compiled')) ?: base_path('bootstrap/cache/lynx-cache.php');
    }

    /**
     * Plain values for serve.php, which runs before the framework.
     *
     * @return array{enabled: bool, driver: string, path: string, hosts: list<string>, bypass_cookies: list<string>, ignored_parameters: list<string>, edge_ttl: int, debug: bool}
     */
    public function compiled(): array
    {
        return [
            'enabled' => config('lynx-cache.pages.enabled') === true,
            'driver' => Value::string(config('lynx-cache.pages.driver'), 'files'),
            'path' => $this->path(),
            'hosts' => $this->hosts(),
            'bypass_cookies' => $this->bypassCookies(),
            'ignored_parameters' => Value::strings(config('lynx-cache.pages.ignored_parameters')),
            'edge_ttl' => $this->edgeTtl(),
            'debug' => config('lynx-cache.pages.debug') === true,
        ];
    }
}
