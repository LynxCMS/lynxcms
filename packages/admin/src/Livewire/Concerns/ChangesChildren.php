<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

/**
 * Status changes and trash for the Children tab: one child at a time from its row, or the selected
 * ones at once (ChangesPages). Ids come from the browser, so each must be a child of the page.
 */
trait ChangesChildren
{
    use ChangesPages;

    public ?string $status = null;

    /** Flip a child's unpublished or hidden flag. */
    public function toggle(int $id, string $flag): void
    {
        if (! in_array($flag, ['unpublished', 'hidden'], true)) {
            return;
        }

        $page = $this->child($id);
        $has = $page->hasStatus(PageStatus::NAMES[$flag]);
        lynx()->pages->setStatus($page, ...($has ? ['remove' => [$flag]] : ['add' => [$flag]]));
    }

    private function changeablePage(int $id): Page
    {
        return $this->child($id);
    }

    private function trashConfirming(): void
    {
        $this->moving = [];
    }

    /** A child of the page, by an id the browser sent. */
    private function child(int $id): Page
    {
        $page = lynx()->pages->get($id);

        if (! $page instanceof Page || $page->parentId !== $this->pageId) {
            throw ValidationException::withMessages(['page' => __('lynx-admin::children.not_a_child')]);
        }

        return $page;
    }
}
