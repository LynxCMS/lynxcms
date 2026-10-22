<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use Illuminate\Contracts\Cache\Repository;
use LynxCms\Core\Support\Value;

/**
 * Pages in a Laravel cache store (Redis, Laravel Cloud KV) that every server shares. Keys carry
 * the generation, so a flush is one write that every server sees at once.
 *
 * @internal
 */
final readonly class CachePageStore implements PageStore
{
    public function __construct(private Repository $cache, private Generation $generation) {}

    public function get(CacheAddress $address): ?CachedPage
    {
        $entry = Value::map($this->cache->get($this->keyOf($address)));

        if ($entry === []) {
            return null;
        }

        $page = new CachedPage(Value::string($entry['html'] ?? null), Value::int($entry['created'] ?? null), Value::nullableInt($entry['expires'] ?? null));

        return $page->isExpired(now()->getTimestamp()) ? null : $page;
    }

    public function put(CacheAddress $address, CachedPage $page): void
    {
        $entry = ['html' => $page->html, 'created' => $page->createdAt, 'expires' => $page->expiresAt];

        if ($page->expiresAt === null) {
            $this->cache->forever($this->keyOf($address), $entry);

            return;
        }

        $this->cache->put($this->keyOf($address), $entry, max(1, $page->expiresAt - now()->getTimestamp()));
    }

    public function forget(CacheAddress $address): void
    {
        $this->cache->forget($this->keyOf($address));
    }

    public function flush(): void
    {
        // Old keys are never read again; the store's eviction drops them.
        $this->generation->bump();
    }

    private function keyOf(CacheAddress $address): string
    {
        return 'lynx-cache:page:'.$this->generation->current().':'.sha1($address->key());
    }
}
