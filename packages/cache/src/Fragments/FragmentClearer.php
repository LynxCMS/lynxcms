<?php

declare(strict_types=1);

namespace LynxCms\Cache\Fragments;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use LynxCms\Cache\FragmentEntry;
use LynxCms\Cache\Pages\Generation;

/**
 * Drops stored fragments through their index: all, by name, one, or those depending on changes.
 *
 * @internal
 */
final readonly class FragmentClearer
{
    public function __construct(private Repository $cache, private Generation $generation) {}

    public function clearAll(): int
    {
        return $this->clear(FragmentEntry::query());
    }

    public function clearName(string $name): int
    {
        return $this->clear(FragmentEntry::query()->where('name', $name));
    }

    public function clearEntry(int $id): int
    {
        return $this->clear(FragmentEntry::query()->whereKey($id));
    }

    /** @param list<string> $dependencies e.g. page:12, template:course; "any" is always added */
    public function clearDependingOn(array $dependencies): int
    {
        $query = FragmentEntry::query();
        FragmentEntry::dependingOn($query, [FragmentEntry::ANY, ...$dependencies]);

        return $this->clear($query);
    }

    /** @param Builder<FragmentEntry> $entries */
    private function clear(Builder $entries): int
    {
        $this->generation->touch();
        $count = 0;

        $entries->each(function (FragmentEntry $entry) use (&$count): void {
            $this->cache->forget($entry->cache_key);
            $entry->delete();
            $count++;
        });

        return $count;
    }
}
