<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/**
 * A page went to the trash, with every page below it.
 *
 * @api
 */
final readonly class PageTrashed implements ContentChanged
{
    /**
     * @param  list<int>  $pageIds  the page and every page under it
     */
    public function __construct(public int $pageId, public int $fromParentId, public array $pageIds) {}

    public function affectedPages(): array
    {
        return array_values(array_unique([...$this->pageIds, $this->fromParentId]));
    }
}
