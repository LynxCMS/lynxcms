<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/** @api */
final readonly class PageRestored implements ContentChanged
{
    public function __construct(public int $pageId, public int $toParentId) {}

    public function affectedPages(): array
    {
        return [$this->pageId, $this->toParentId];
    }
}
