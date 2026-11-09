<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use LynxCms\Admin\Editor\ParentTemplates;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Moving the selected children of the Children tab under another parent, chosen in a page picker.
 * Each page moves with pages.move, which checks the user's permissions, the parent's template
 * rules and the names already under it; a page it refuses stays (ChangesChildren::each). The page
 * search offers only pages whose templates take every page being moved (ParentTemplates).
 */
trait MovesChildren
{
    /** @var list<int> children waiting for their new parent */
    #[Locked]
    public array $moving = [];

    /** @var list<int> the new parent chosen in the page picker */
    public array $moveTo = [];

    public function chooseParent(): void
    {
        $this->moving = array_values(array_map(fn (Page $page): int => (int) $page->id, array_map($this->child(...), Value::ints($this->selected))));
        $this->moveTo = [];
        $this->trashing = [];
    }

    public function cancelMove(): void
    {
        $this->moving = [];
        $this->moveTo = [];
    }

    public function move(): void
    {
        $parent = Value::ints($this->moveTo)[0] ?? null;

        if ($parent === null) {
            throw ValidationException::withMessages(['moveTo' => __('lynx-admin::children.move_choose_first')]);
        }

        $this->selected = $this->moving;
        $this->cancelMove();
        $this->report($this->each(fn (Page $page): mixed => lynx()->pages->move($page, $parent)));
    }

    /**
     * Templates the new parent may use; empty while nothing is being moved.
     *
     * @return list<string>
     */
    private function moveParents(): array
    {
        if ($this->moving === []) {
            return [];
        }

        return app(ParentTemplates::class)->for(lynx()->pages->find('id='.implode('|', $this->moving).', include=all'), [$this->pageId], $this->moveExcludedBelow());
    }

    /**
     * The pages being moved and modules' branches (menus, forms): never a new parent, nor anything
     * below them. The page itself is left out too, but not its other children.
     *
     * @return list<int>
     */
    private function moveExcludedBelow(): array
    {
        return [...$this->moving, ...app(ParentTemplates::class)->moduleBranches()];
    }
}
