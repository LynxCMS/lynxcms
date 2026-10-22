<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use Illuminate\Database\Eloquent\Builder;
use LynxCms\Cache\PageCacheEntry;

/**
 * Drops stored pages: all of them, those of some pages or a template (query variants included),
 * or those past their lifetime.
 *
 * @internal
 */
final readonly class PageCacheClearer
{
    public function __construct(private PageStore $store, private Generation $generation) {}

    public function clearAll(): void
    {
        $this->store->flush();
        PageCacheEntry::query()->delete();
    }

    /** @param list<int> $pageIds */
    public function clearPages(array $pageIds): void
    {
        if ($pageIds !== []) {
            $this->clear(PageCacheEntry::query()->whereIn('page_id', $pageIds));
        }
    }

    public function clearTemplate(string $template): void
    {
        $this->clear(PageCacheEntry::query()->where('template', $template));
    }

    /** Drops expired pages, which web servers would otherwise keep sending; returns how many. */
    public function prune(): int
    {
        return $this->clear(PageCacheEntry::query()->where('expires_at', '<=', now()));
    }

    /** @param Builder<PageCacheEntry> $entries */
    private function clear(Builder $entries): int
    {
        $this->generation->touch();
        $count = 0;

        $entries->each(function (PageCacheEntry $entry) use (&$count): void {
            $this->store->forget($entry->address());
            $entry->delete();
            $count++;
        });

        return $count;
    }
}
