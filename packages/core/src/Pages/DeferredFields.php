<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

/**
 * The pages PageLoader hydrated together, for fields it did not load up front (ADR-0067). The first
 * read of such a field on any of them loads it for every page of the batch still without it, in one
 * query, so a listing never costs one query per page as ProcessWire's lazy fields do.
 *
 * @internal
 */
final class DeferredFields
{
    /** @var array<int, Page> */
    private array $pages = [];

    public function __construct(private readonly PageLoader $loader) {}

    public function add(Page $page): void
    {
        $this->pages[(int) $page->id] = $page;
    }

    /**
     * Loads $field for the batch and returns $page's values (language id => value). $page may be a
     * copy of a batch page, which gets its own values back.
     *
     * @return array<int, mixed>
     */
    public function load(string $field, Page $page): array
    {
        $waiting = array_filter($this->pages, fn (Page $other): bool => $other !== $page && $other->isDeferred($field));
        $ids = array_values(array_unique([...array_keys($waiting), (int) $page->id]));
        $values = $this->loader->fieldValues($field, $ids);

        foreach ($waiting as $id => $other) {
            $other->absorbDeferred($field, $values[$id] ?? []);
        }

        return $values[(int) $page->id] ?? [];
    }
}
