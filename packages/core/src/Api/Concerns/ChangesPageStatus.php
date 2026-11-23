<?php

declare(strict_types=1);

namespace LynxCms\Core\Api\Concerns;

use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;

/**
 * Verbs for the page status flags editors change most: each runs pages.status through
 * setStatus(), with its permissions (pages.publish for publishing, pages.status for the rest).
 * The unique flag stays on setStatus().
 *
 * @internal
 */
trait ChangesPageStatus
{
    /** Clears the unpublished flag, so visitors see the page. */
    public function publish(Page|int|string $page, bool $dryRun = false): Page|ActionResult
    {
        return $this->setStatus($page, remove: ['unpublished'], dryRun: $dryRun);
    }

    /** Sets the unpublished flag: only the admin and code see the page. */
    public function unpublish(Page|int|string $page, bool $dryRun = false): Page|ActionResult
    {
        return $this->setStatus($page, add: ['unpublished'], dryRun: $dryRun);
    }

    /** Leaves the page out of menus, lists and search; its address still works. */
    public function hide(Page|int|string $page, bool $dryRun = false): Page|ActionResult
    {
        return $this->setStatus($page, add: ['hidden'], dryRun: $dryRun);
    }

    public function unhide(Page|int|string $page, bool $dryRun = false): Page|ActionResult
    {
        return $this->setStatus($page, remove: ['hidden'], dryRun: $dryRun);
    }

    /** Refuses edits, moves and deletion until the page is unlocked. */
    public function lock(Page|int|string $page, bool $dryRun = false): Page|ActionResult
    {
        return $this->setStatus($page, add: ['locked'], dryRun: $dryRun);
    }

    public function unlock(Page|int|string $page, bool $dryRun = false): Page|ActionResult
    {
        return $this->setStatus($page, remove: ['locked'], dryRun: $dryRun);
    }
}
