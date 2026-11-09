<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\ChildList;
use LynxCms\Admin\Livewire\Concerns\ChangesChildren;
use LynxCms\Admin\Livewire\Concerns\MovesChildren;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Query\PageScope;

/**
 * The Children tab of the page editor: the page's children, with a search, a status filter and
 * pages of PER_PAGE rows, to reorder by drag and to publish, hide or trash one at a time or several
 * at once (ChangesChildren), or to move several under another parent (MovesChildren). Every change
 * runs a pages action, which checks the permissions again.
 *
 * @internal
 */
final class PageChildren extends Component
{
    use ChangesChildren;
    use MovesChildren;
    use Notifies;

    public const int PER_PAGE = 25;

    /** Status filter => the selector clause it adds. */
    public const array FILTERS = ['all' => '', 'published' => 'status!=unpublished', 'unpublished' => 'status=unpublished', 'hidden' => 'status=hidden'];

    /** Sort menu choice => [what pages.sort-children sorts by, descending]. */
    public const array SORTS = [
        'title' => ['title', false],
        'title_desc' => ['title', true],
        'created_desc' => ['created', true],
        'created' => ['created', false],
        'modified_desc' => ['modified', true],
        'published_desc' => ['published', true],
    ];

    #[Locked]
    public int $pageId;

    /** The order chosen in the Sort menu, waiting for confirmation; empty while none is. */
    public string $sortBy = '';

    public string $search = '';

    public string $filter = 'all';

    public int $page = 1;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function mount(int $pageId): void
    {
        $this->pageId = $pageId;
    }

    public function updatedSearch(): void
    {
        $this->resetList();
    }

    public function updatedFilter(): void
    {
        $this->filter = array_key_exists($this->filter, self::FILTERS) ? $this->filter : 'all';
        $this->resetList();
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->selected = [];
    }

    /** Puts every child in the chosen order (pages.sort-children), then forgets the choice. */
    public function sortChildren(): void
    {
        [$by, $descending] = self::SORTS[$this->sortBy] ?? [null, false];
        $this->sortBy = '';

        if ($by !== null && $this->list()->isComplete($this->pageId)) {
            lynx()->pages->sortChildren($this->pageId, $by, $descending);
            $this->resetList();
            $this->notify(__('lynx-admin::children.sorted'));
        }
    }

    /** A drop in the list: the child takes that place among all its siblings. */
    public function reorder(int $id, int $position): void
    {
        if ($this->canReorder()) {
            lynx()->pages->sort($this->child($id), ($this->page - 1) * self::PER_PAGE + max(0, $position));
        }
    }

    public function render(): View
    {
        $children = $this->children();
        $total = $children->total();

        if ($children->count() === 0 && $this->page > 1) {
            $this->page = max(1, (int) ceil($total / self::PER_PAGE));
            $children = $this->children();
        }

        return view('lynx-admin::livewire.page-children', [
            'children' => $children,
            'total' => $total,
            'lastPage' => max(1, (int) ceil($total / self::PER_PAGE)),
            'canReorder' => $this->canReorder(),
            'childCounts' => lynx()->pages->childCounts($children->ids(), $this->list()->everyChild(), $this->scope()),
            'drafts' => array_flip(lynx()->pages->withDrafts($children->ids())),
            'filters' => array_keys(self::FILTERS),
            'sorts' => array_keys(self::SORTS),
            'moveParents' => $this->moveParents(),
            'moveExcluded' => $this->moveExcluded(),
            'canSort' => $total > 1 && $this->list()->isComplete($this->pageId),
        ]);
    }

    private function children(): PageArray
    {
        $start = ($this->page - 1) * self::PER_PAGE;

        return lynx()->pages->find($this->selector().", start={$start}, limit=".self::PER_PAGE, scope: $this->scope());
    }

    /** The page's children as the tree lists them, narrowed by the search and the status filter. */
    private function selector(): string
    {
        $search = trim(str_replace(['"', "\0"], '', $this->search));

        return implode(', ', array_filter([
            $this->list()->selector($this->pageId),
            // Inside a double-quoted value, commas and pipes are plain text; only quotes could end it.
            $search === '' ? '' : 'title%="'.$search.'"',
            self::FILTERS[$this->filter] ?? '',
        ]));
    }

    /** Positions are real only while every child is listed: no search, no filter, nothing out of scope. */
    private function canReorder(): bool
    {
        return $this->search === '' && $this->filter === 'all' && $this->list()->isComplete($this->pageId);
    }

    private function scope(): ?PageScope
    {
        return $this->list()->scope();
    }

    private function list(): ChildList
    {
        return app(ChildList::class);
    }

    private function resetList(): void
    {
        $this->page = 1;
        $this->selected = [];
    }
}
