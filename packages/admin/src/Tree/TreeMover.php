<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;

/**
 * Turns tree gestures (drop, up, down, indent, outdent) into pages.sort and pages.move calls.
 * Positions come from the rendered tree; the actions validate everything again.
 *
 * @internal
 */
final class TreeMover
{
    /** A drop: same parent reorders, another parent moves. */
    public function drop(int $id, int $parentId, int $position): void
    {
        $page = $this->page($id);

        // A trashed branch dropped into the tree is restored there (ADR-0035).
        if ($page->parentId !== null && $page->parentId === lynx()->pages->trashId()) {
            lynx()->pages->restore($page, $parentId);
            lynx()->pages->sort($page, $position);

            return;
        }

        if ($page->parentId === $parentId) {
            lynx()->pages->sort($page, $position);

            return;
        }

        lynx()->pages->move($page, $parentId, $position);
    }

    public function up(int $id, int $position): void
    {
        if ($position > 0) {
            lynx()->pages->sort($id, $position - 1);
        }
    }

    public function down(int $id, int $position): void
    {
        lynx()->pages->sort($id, $position + 1);
    }

    /**
     * Make the page the last child of its previous sibling.
     *
     * @return int|null the new parent's id, or null when there is no previous sibling
     */
    public function indent(int $id, int $parentId, int $position): ?int
    {
        if ($position === 0) {
            return null;
        }

        $previous = lynx()->pages->findOne('parent='.$parentId.', include=all, start='.($position - 1));

        if (! $previous instanceof Page) {
            return null;
        }

        lynx()->pages->move($id, $previous);

        return (int) $previous->id;
    }

    /** Make the page the next sibling of its parent. */
    public function outdent(int $id, int $parentId, int $parentPosition): void
    {
        $parent = $this->page($parentId);

        if ($parent->parentId === null) {
            throw ValidationException::withMessages(['page' => 'Pages cannot be placed beside the home page.']);
        }

        lynx()->pages->move($id, $parent->parentId, $parentPosition + 1);
    }

    private function page(int $id): Page
    {
        return lynx()->pages->get($id) ?? throw ValidationException::withMessages(['page' => "Page [{$id}] does not exist."]);
    }
}
