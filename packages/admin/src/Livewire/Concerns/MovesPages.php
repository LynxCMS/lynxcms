<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Closure;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use LynxCms\Admin\Tree\TreeMover;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Move mode of the page tree: drag and drop (wire:sort), keyboard moves, and keeping the moved
 * row in view across pager pages. Writes go through TreeMover and the pages API.
 *
 * @internal
 */
trait MovesPages
{
    #[Locked]
    public ?int $moving = null;

    public function startMove(int $id): void
    {
        $this->moving = $id;
        $this->error = null;
    }

    public function cancelMove(): void
    {
        $this->moving = null;
    }

    /**
     * wire:sort handler. The group id is the parent the item was dropped into, or
     * "{parent}:{page}" for a pager number, which places the page at the top of that pager page.
     */
    public function handleSort(int|string $id, int|string $position, int|string $groupId): void
    {
        [$parent, $page] = array_map(Value::int(...), [...explode(':', (string) $groupId), 0]);
        $absolute = $page > 0
            ? ($page - 1) * $this->perPage
            : ($this->currentPage($parent) - 1) * $this->perPage + Value::int($position);

        $this->attempt(function () use ($id, $parent, $absolute): void {
            $this->movablePage(Value::int($id));
            $this->assertInTree($parent);
            $this->assertReorderable($parent);
            (new TreeMover)->drop(Value::int($id), $parent, $absolute);
        });
        $this->followTo($parent, $absolute);
        $this->moving = null;
    }

    public function moveUp(int $id, int $parentId, int $position): void
    {
        $this->attempt(function () use ($id, $position): void {
            $page = $this->movablePage($id);
            $this->assertReorderable((int) $page->parentId);
            (new TreeMover)->up($id, $position);
            $this->followTo((int) $page->parentId, max(0, $position - 1));
        });
    }

    public function moveDown(int $id, int $parentId, int $position): void
    {
        $this->attempt(function () use ($id, $position): void {
            $page = $this->movablePage($id);
            $this->assertReorderable((int) $page->parentId);
            (new TreeMover)->down($id, $position);
            $this->followTo((int) $page->parentId, $position + 1);
        });
    }

    /** The parent comes from the database; the position is the row's place in the rendered tree. */
    public function indent(int $id, int $parentId, int $position): void
    {
        $this->attempt(function () use ($id, $position): void {
            $page = $this->movablePage($id);
            $this->assertReorderable((int) $page->parentId);
            $newParent = (new TreeMover)->indent($id, (int) $page->parentId, $position);

            if ($newParent !== null && ! in_array($newParent, $this->expanded, true)) {
                $this->expanded[] = $newParent;
            }
        });
    }

    public function outdent(int $id, int $parentId, int $parentPosition): void
    {
        $this->attempt(function () use ($id, $parentPosition): void {
            $page = $this->movablePage($id);

            if ($page->parentId === $this->rootPage()->id) {
                throw ValidationException::withMessages(['page' => 'The page is already at the top level of this tree.']);
            }

            $this->assertReorderable((int) lynx()->pages->get((int) $page->parentId)?->parentId);
            (new TreeMover)->outdent($id, (int) $page->parentId, $parentPosition);
        });
    }

    /** Show the pager page that holds a position, so a moved row stays in view. */
    private function followTo(int $parentId, int $position): void
    {
        if ($this->error === null && intdiv($position, $this->perPage) + 1 !== $this->currentPage($parentId)) {
            $this->pages[$parentId] = intdiv($position, $this->perPage) + 1;
        }
    }

    private function currentPage(int $parentId): int
    {
        return max(1, Value::int($this->pages[$parentId] ?? 1, 1));
    }

    abstract protected function attempt(Closure $write): void;

    abstract protected function movablePage(int $id): Page;

    abstract protected function assertInTree(int $id): void;

    abstract protected function assertReorderable(int $parentId): void;

    abstract protected function rootPage(): Page;
}
