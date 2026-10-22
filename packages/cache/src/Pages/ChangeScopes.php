<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

use LynxCms\Cache\PageCacheEntry;
use LynxCms\Core\Support\Value;

/**
 * Which pages a change to some pages clears, from each page's template rule (ProCache's "cache
 * clearing behaviors"). Null means every page. What the index stored about a page is used
 * first, so pages are loaded only when a changed page was never stored.
 *
 * @internal
 */
final readonly class ChangeScopes
{
    public function __construct(private TemplateRules $rules) {}

    /**
     * @param  list<int>  $changedIds
     * @return list<int>|null
     */
    public function pagesToClear(array $changedIds): ?array
    {
        if (! $this->rules->hasNarrowRules()) {
            return null;
        }

        $ids = [];

        foreach ($changedIds as $id) {
            $scoped = $this->scopedIds($id);

            if ($scoped === null) {
                return null;
            }

            $ids = [...$ids, ...$scoped];
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int>|null */
    private function scopedIds(int $id): ?array
    {
        $facts = $this->factsAbout($id);
        $rule = $facts === null ? null : $this->rules->for($facts['template']);

        if ($facts === null || $rule === null || $rule->clearsEverything()) {
            return null;
        }

        $ids = [];

        foreach ($rule->clear as $scope) {
            $ids = [...$ids, ...$this->idsIn($scope, $id, $facts)];
        }

        return $ids;
    }

    /** @return array{template: string, parent: ?int, ancestors: list<int>}|null */
    private function factsAbout(int $id): ?array
    {
        $entry = PageCacheEntry::query()->where('page_id', $id)->first();

        if ($entry !== null) {
            return ['template' => $entry->template, 'parent' => $entry->parent_id, 'ancestors' => $entry->ancestorIds()];
        }

        $page = lynx()->pages->get($id);

        return $page === null ? null : ['template' => $page->template()->name, 'parent' => $page->parentId, 'ancestors' => Ancestors::idsOf($page)];
    }

    /**
     * @param  array{template: string, parent: ?int, ancestors: list<int>}  $facts
     * @return list<int>
     */
    private function idsIn(string $scope, int $id, array $facts): array
    {
        return match ($scope) {
            'self' => [$id],
            'parents' => $facts['ancestors'],
            'children' => $this->indexedChildrenOf($id),
            'siblings' => $facts['parent'] === null ? [] : array_values(array_diff($this->indexedChildrenOf($facts['parent']), [$id])),
            'home' => $this->homeId(),
            default => [],
        };
    }

    /**
     * Only stored pages need clearing, so the index answers without loading pages.
     *
     * @return list<int>
     */
    private function indexedChildrenOf(int $parentId): array
    {
        return Value::ints(PageCacheEntry::query()->where('parent_id', $parentId)->distinct()->pluck('page_id')->all());
    }

    /** @return list<int> the stored page without a parent */
    private function homeId(): array
    {
        return Value::ints(PageCacheEntry::query()->whereNull('parent_id')->distinct()->pluck('page_id')->all());
    }
}
