<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/** @api */
final readonly class PageMoved implements ContentChanged
{
    public function __construct(public int $pageId, public ?int $fromParentId, public int $toParentId) {}

    public function affectedPages(): array
    {
        return array_values(array_unique(array_filter([$this->pageId, $this->fromParentId, $this->toParentId], fn (?int $id): bool => $id !== null)));
    }
}
