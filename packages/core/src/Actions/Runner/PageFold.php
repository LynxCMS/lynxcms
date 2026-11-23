<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

use LynxCms\Core\Events\ContentBatchChanged;

/**
 * The pages of muted content changes, folded into one ContentBatchChanged (ADR-0145).
 *
 * @internal
 */
final class PageFold
{
    /** Past this many pages the batch means every page: listeners clear everything at once. */
    public const int LIMIT = 1000;

    /** @var array<int, true>|null page id => true; null means every page */
    private ?array $pages = [];

    private bool $isEmpty = true;

    /**
     * @param  list<int>|null  $pages  an event's affected pages; null when any page may show it
     */
    public function add(?array $pages): void
    {
        $this->isEmpty = false;

        if ($pages === null || $this->pages === null) {
            $this->pages = null;

            return;
        }

        $this->pages += array_fill_keys($pages, true);

        if (count($this->pages) > self::LIMIT) {
            $this->pages = null;
        }
    }

    public function merge(self $other): void
    {
        if (! $other->isEmpty) {
            $this->add($other->pages === null ? null : array_keys($other->pages));
        }
    }

    public function isEmpty(): bool
    {
        return $this->isEmpty;
    }

    public function event(): ContentBatchChanged
    {
        if ($this->pages === null) {
            return new ContentBatchChanged(null);
        }

        $ids = array_keys($this->pages);
        sort($ids);

        return new ContentBatchChanged($ids);
    }
}
