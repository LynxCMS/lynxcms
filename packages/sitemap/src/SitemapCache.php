<?php

declare(strict_types=1);

namespace LynxCms\Sitemap;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LynxCms\Core\Support\Value;

/**
 * Built sitemap files, kept under a version that any content change replaces, so old files
 * are never served and never need deleting one by one.
 *
 * @internal
 */
final class SitemapCache
{
    private const string VERSION_KEY = 'lynx-sitemap.version';

    public function version(): string
    {
        return Value::string($this->store()->rememberForever(self::VERSION_KEY, fn (): string => Str::random(12)));
    }

    public function clear(): void
    {
        $this->store()->forever(self::VERSION_KEY, Str::random(12));
    }

    /** @param  Closure(): string  $build */
    public function remember(string $name, Closure $build): string
    {
        $ttl = Value::int(config('lynx-sitemap.cache.ttl'), 86400);

        return Value::string($this->store()->remember('lynx-sitemap.'.$this->version().'.'.$name, $ttl, $build));
    }

    private function store(): Repository
    {
        $store = config('lynx-sitemap.cache.store');

        return Cache::store(is_string($store) ? $store : null);
    }
}
