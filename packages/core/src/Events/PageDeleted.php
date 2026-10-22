<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/** @api */
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
