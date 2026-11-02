<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use WeakReference;

/**
 * The pages PageLoader hydrated together, for fields it did not load up front (ADR-0067). The first
 * read of such a field on any of them loads it for every page of the batch still without it, in one
 * query, so a listing never costs one query per page as ProcessWire's lazy fields do.
 *
 * Every page of a batch points here, so this holds no loader and no page outright: dump(), dd()
 * and Tinkerwell follow what a page holds, and would print the registries and every sibling.
 * A sibling nothing else holds any more needs no field loaded.
 *
 * @internal
 */
final class DeferredFields
{
    /** @var array<int, WeakReference<Page>> */
    private array $pages = [];

    public function add(Page $page): void
    {
        $this->pages[(int) $page->id] = WeakReference::create($page);
    }

    /**
     * Loads $field for the batch and returns $page's values (language id => value). $page may be a
     * copy of a batch page, which gets its own values back.
     *
     * @return array<int, mixed>
     */
    public function load(string $field, Page $page): array
    {
        $waiting = array_filter(
            array_map(fn (WeakReference $reference): ?Page => $reference->get(), $this->pages),
            fn (?Page $other): bool => $other instanceof Page && $other !== $page && $other->isDeferred($field),
        );
        $ids = array_values(array_unique([...array_keys($waiting), (int) $page->id]));
        $values = app(PageLoader::class)->fieldValues($field, $ids);

        foreach ($waiting as $id => $other) {
            $other->absorbDeferred($field, $values[$id] ?? []);
        }

        return $values[(int) $page->id] ?? [];
    }
}
