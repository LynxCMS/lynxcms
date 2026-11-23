<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/**
 * A page came back from the trash, under the parent it was restored to.
 *
 * @api
 */
final readonly class PageRestored implements ContentChanged
{
    public function __construct(public int $pageId, public int $toParentId) {}

    public function affectedPages(): array
    {
        return [$this->pageId, $this->toParentId];
    }
}
