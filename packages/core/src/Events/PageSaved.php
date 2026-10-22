<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/** @api */
final readonly class PageSaved implements ContentChanged
{
    public function __construct(public int $pageId, public bool $created) {}

    public function affectedPages(): array
    {
        return [$this->pageId];
    }
}
