<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/**
 * A save or a template switch added or removed references to other pages (ADR-0145), dispatched
 * once per page after the action commits. Each entry has linked_page_id, field (the field's name)
 * and item_key (the repeater or matrix item that holds the reference, or null). The linked pages
 * count as changed too, since a page may show what links to it.
 *
 * @api
 */
final readonly class PageReferencesChanged implements ContentChanged
{
    /**
     * @param  list<array{linked_page_id: int, field: string, item_key: string|null}>  $added
     * @param  list<array{linked_page_id: int, field: string, item_key: string|null}>  $removed
     */
    public function __construct(public int $pageId, public array $added, public array $removed) {}

    public function affectedPages(): array
    {
        $linked = array_map(fn (array $entry): int => $entry['linked_page_id'], [...$this->added, ...$this->removed]);

        return array_values(array_unique([$this->pageId, ...$linked]));
    }
}
