<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/**
 * A page was created ($created) or saved, dispatched after the action commits.
 *
 * @api
 */
final readonly class PageSaved implements ContentChanged
{
    public function __construct(public int $pageId, public bool $created) {}

    public function affectedPages(): array
    {
        return [$this->pageId];
    }
}
