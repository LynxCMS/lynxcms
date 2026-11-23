<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/**
 * A page was deleted for good, with every page below it: their ids, dispatched after the action commits.
 *
 * @api
 */
final readonly class PageDeleted implements ContentChanged
{
    /**
     * @param  list<int>  $pageIds
     */
    public function __construct(public array $pageIds) {}

    public function affectedPages(): array
    {
        return $this->pageIds;
    }
}
