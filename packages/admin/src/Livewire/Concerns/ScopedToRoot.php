<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;

/**
 * Keeps the page tree's writes inside its root. Ids come from the browser, so every write
 * checks them here instead of trusting what the rendered tree showed.
 *
 * @internal
 */
trait ScopedToRoot
{
    /** A page strictly below the tree's root: the only pages this tree may move, hide or delete. */
    protected function movablePage(int $id): Page
    {
        $root = (int) $this->rootPage()->id;
        $page = $id !== $root ? lynx()->pages->get($id) : null;
        $isInside = $page instanceof Page && lynx()->pages->count("id={$id}, has_parent={$root}, include=all") === 1;

        return $isInside ? $page : throw ValidationException::withMessages(['page' => __('lynx-admin::pages.tree.outside_tree')]);
    }

    /** The root itself or a page below it, e.g. where a page is dropped. */
    protected function assertInTree(int $id): void
    {
        if ($id !== $this->rootPage()->id) {
            $this->movablePage($id);
        }
    }

    abstract protected function rootPage(): Page;
}
