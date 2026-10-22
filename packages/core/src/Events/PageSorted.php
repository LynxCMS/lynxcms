<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/** @api */
final readonly class PageSorted implements ContentChanged
{
    public function __construct(public int $pageId, public int $parentId, public int $fromPosition, public int $position) {}

    public function affectedPages(): array
    {
        return [$this->pageId, $this->parentId];
    }
}
