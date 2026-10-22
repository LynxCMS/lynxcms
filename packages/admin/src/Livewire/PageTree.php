<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Livewire\Concerns\MovesPages;
use LynxCms\Admin\Livewire\Concerns\PageQuickActions;
use LynxCms\Admin\Livewire\Concerns\ScopedToRoot;
use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Admin\Tree\TreeBranch;
use LynxCms\Admin\Tree\TreeLoader;
use LynxCms\Admin\Tree\TreePresenter;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use UnexpectedValueException;

/**
 * ProcessWire-style page tree: lazy branches and numbered pagers. Move mode (MovesPages), quick
 * status and delete actions (PageQuickActions) and the root scope of every write (ScopedToRoot)
 * live in traits. Every write goes through the pages API. The menu builder reuses it with another root.
 *
 * @api
 */
final class PageTree extends Component
{
    use MovesPages;
    use PageQuickActions;
    use ScopedToRoot;

    /**
     * SortableJS options: drag only by the move-mode handle, nested lists, light animation.
     * onMove ignores the pager row (it sits inside the parent's list item), so hovering it does
     * not reorder the parent level; only the pager's own drop lists accept the page there.
     */
    public const string SORTABLE_CONFIG = "{ handle: '.lynx-tree-handle', fallbackOnBody: true, swapThreshold: 0.65, animation: 120, "
        ."onMove: (event, pointer) => ! pointer.target.closest('.lynx-tree-pager') || event.to.classList.contains('lynx-pager-drop') }";

    #[Locked]
    public string $root = '/';

    #[Locked]
    public bool $sortable = true;

    #[Locked]
    public int $perPage = 20;

    /** @var class-string<TreePresenter>|null what each row shows; null uses the title and template */
    #[Locked]
    public ?string $presenter = null;

    /** Event prefix: Edit and New dispatch "{prefix}-edit" and "{prefix}-new" instead of following routes. */
    #[Locked]
    public ?string $events = null;

    /**
     * Ids of open pages, kept in the URL so a reload shows the same tree, and in the session so
     * coming back from another screen does too.
     *
     * @var list<int>
     */
    #[Url(as: 'open')]
    public array $expanded = [];

    /** @var array<int, int> pager page per parent id, kept in the URL */
    #[Url(as: 'page')]
    public array $pages = [];

    #[Locked]
    public ?string $error = null;

    /** Runs on mount and on every Livewire update, so the gate also guards /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function mount(string $root = '/', bool $sortable = true, ?int $perPage = null, ?string $presenter = null, ?string $events = null): void
    {
        $this->root = $root;
        $this->sortable = $sortable;
        $this->presenter = $presenter !== null && is_subclass_of($presenter, TreePresenter::class) ? $presenter : null;
        $this->events = $events !== null && preg_match('/^[a-z][a-z0-9-]*$/', $events) === 1 ? $events : null;
        $this->perPage = max(1, $perPage ?? Value::int(config('lynx-admin.tree.per_page'), 20));
        $this->expanded = $this->expanded !== [] ? $this->expanded : (Value::ints(session($this->sessionKey())) ?: [(int) $this->rootPage()->id]);
        $this->remember();
    }

    public function toggle(int $id): void
    {
        $this->expanded = in_array($id, $this->expanded, true)
            ? array_values(array_diff($this->expanded, [$id]))
            : [...$this->expanded, $id];
        $this->remember();
    }

    /** Pages past the end are clamped to the last page when the tree is loaded. */
    public function goToPage(int $parentId, int $page): void
    {
        $this->pages[$parentId] = max(1, $page);
    }

    /** Another component changed pages (e.g. the menu editor saved an item): reload, optionally opening a branch. */
    #[On('lynx-tree-refresh')]
    public function refresh(?int $expand = null): void
    {
        if ($expand !== null && ! in_array($expand, $this->expanded, true)) {
            $this->expanded[] = $expand;
            $this->remember();
        }
    }

    /** Each tree (the page tree, a menu's tree) remembers its own open branches. */
    private function sessionKey(): string
    {
        return 'lynx-admin.tree.'.sha1($this->root.'|'.$this->presenter);
    }

    private function remember(): void
    {
        session()->put($this->sessionKey(), Value::ints($this->expanded));
    }

    public function render(): View
    {
        return view('lynx-admin::livewire.page-tree', ['tree' => $this->tree(), 'usesTrash' => $this->usesTrash()]);
    }

    private function tree(): TreeBranch
    {
        $pages = [];

        foreach ($this->pages as $parentId => $page) {
            $pages[Value::int($parentId)] = Value::int($page, 1);
        }

        return $this->treeLoader($pages)->branch($this->rootPage());
    }

    /**
     * @param  array<int, int>  $pages  current pager page per parent id
     */
    private function treeLoader(array $pages = []): TreeLoader
    {
        return new TreeLoader(Value::ints($this->expanded), $pages, $this->perPage, $this->presenter(), app(AdminAccess::class)->treeScope());
    }

    /** Reordering needs every sibling in view: positions among visible rows are not real positions. */
    protected function assertReorderable(int $parentId): void
    {
        if (! $this->treeLoader()->isComplete($parentId)) {
            throw ValidationException::withMessages(['page' => 'Some pages here are hidden from you, so pages cannot be reordered here.']);
        }
    }

    private function presenter(): TreePresenter
    {
        $presenter = $this->presenter !== null ? app($this->presenter) : null;

        return $presenter instanceof TreePresenter ? $presenter : new DefaultPresenter;
    }

    protected function rootPage(): Page
    {
        return lynx()->pages->get($this->root) ?? throw new UnexpectedValueException("Page tree root [{$this->root}] does not exist.");
    }

    /** Run a write; a validation failure becomes the visible error instead of a 500. */
    protected function attempt(Closure $write): void
    {
        $this->error = null;

        try {
            $write();
        } catch (ValidationException $exception) {
            $this->error = Value::string(Arr::first(Arr::flatten($exception->errors())), $exception->getMessage());
        }
    }
}
