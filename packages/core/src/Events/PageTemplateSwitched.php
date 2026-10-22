<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/** @api */
final readonly class PageTemplateSwitched implements ContentChanged
{
    public function __construct(public int $pageId, public string $from, public string $to) {}

    public function affectedPages(): array
    {
        return [$this->pageId];
    }
}
