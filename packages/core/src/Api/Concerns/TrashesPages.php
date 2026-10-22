<?php

declare(strict_types=1);

namespace LynxCms\Core\Api\Concerns;

use LynxCms\Core\Actions\Pages\EmptyTrash;
use LynxCms\Core\Actions\Pages\RestorePage;
use LynxCms\Core\Actions\Pages\TrashPage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;

/**
 * The trash part of the pages API (ADR-0035).
 *
 * @api
 */
trait TrashesPages
{
    /** The trash page's id, or null until something was trashed (ADR-0035). */
    public function trashId(): ?int
    {
        return $this->trash->rootId();
    }

    /** Move a page and everything under it to the trash (ADR-0035). */
    public function trash(Page|int|string $page, bool $dryRun = false): Page|ActionResult
    {
        return $this->runner->run(TrashPage::class, ['page' => $this->ref($page)], $dryRun)->valueOrDryRun(Page::class);
    }

    /** Take a trashed page back out, where it was or under $parent. */
    public function restore(Page|int|string $page, Page|int|string|null $parent = null, bool $dryRun = false): Page|ActionResult
    {
        $input = ['page' => $this->ref($page)] + ($parent === null ? [] : ['parent' => $this->ref($parent)]);

        return $this->runner->run(RestorePage::class, $input, $dryRun)->valueOrDryRun(Page::class);
    }

    /**
     * Delete trashed branches for good: all, or the one whose top page is $page.
     *
     * @return array{deleted: list<int>, blocked: list<array{page: int, locked: list<int>}>, denied: list<int>}|ActionResult
     */
    public function emptyTrash(?int $page = null, bool $dryRun = false): array|ActionResult
    {
        $result = $this->runner->run(EmptyTrash::class, $page === null ? [] : ['page' => $page], $dryRun);

        if ($result->dryRun) {
            return $result;
        }

        /** @var array{deleted: list<int>, blocked: list<array{page: int, locked: list<int>}>, denied: list<int>} $value */
        $value = $result->arrayValue();

        return $value;
    }
}
