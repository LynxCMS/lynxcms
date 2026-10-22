<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/**
 * A page was published, unpublished, hidden, locked or otherwise flagged.
 *
 * @api
 */
final readonly class PageStatusChanged implements ContentChanged
{
    /**
     * @param  list<string>  $added  status names, e.g. ["unpublished"]
     * @param  list<string>  $removed
     */
    public function __construct(public int $pageId, public array $added, public array $removed) {}

    public function affectedPages(): array
    {
        return [$this->pageId];
    }
}
