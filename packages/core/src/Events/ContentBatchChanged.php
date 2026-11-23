<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/**
 * Content changed while events were muted (lynx()->withoutEvents(), ADR-0145): one event for every
 * muted ContentChanged, dispatched after the muting ends and the actions commit, so caches, menus
 * and search engines notice. Null pages mean any page may have changed.
 *
 * @api
 */
final readonly class ContentBatchChanged implements ContentChanged
{
    /**
     * @param  list<int>|null  $pageIds
     */
    public function __construct(public ?array $pageIds) {}

    public function affectedPages(): ?array
    {
        return $this->pageIds;
    }
}
