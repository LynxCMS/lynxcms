<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

/**
 * Where stored pages live: files on this server, or a cache store shared by every server.
 *
 * @internal
 */
interface PageStore
{
    /** The page at this address, or null when there is none or it expired. */
    public function get(CacheAddress $address): ?CachedPage;

    public function put(CacheAddress $address, CachedPage $page): void;

    public function forget(CacheAddress $address): void;

    /** Drops every stored page and moves the generation on, so pages rendering now are not stored. */
    public function flush(): void;
}
