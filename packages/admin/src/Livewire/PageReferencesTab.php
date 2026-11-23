<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Editor\ReferenceRows;

/**
 * The editor's References tab (ADR-0145): the pages that reference this one, read from the page
 * references index when the tab opens, 50 at a time. Saving the page does not reload it.
 *
 * @internal
 */
#[Lazy]
final class PageReferencesTab extends Component
{
    public const int PER_PAGE = 50;

    #[Locked]
    public int $pageId;

    #[Locked]
    public int $perPage = self::PER_PAGE;

    #[Locked]
    public int $limit = self::PER_PAGE;

    /** Set by boot() on every request: the signed in user's rights. */
    private AdminAccess $access;

    /** Runs on mount and on every Livewire update, so the gate also guards /livewire/update. */
    public function boot(AdminAccess $access): void
    {
        $this->access = $access;
        $access->authorizeScreen();
    }

    public function mount(int $pageId, int $perPage = self::PER_PAGE): void
    {
        $this->pageId = $pageId;
        $this->perPage = max(1, $perPage);
        $this->limit = $this->perPage;
    }

    public function showMore(): void
    {
        $this->limit += $this->perPage;
    }

    public function placeholder(): View
    {
        return view('lynx-admin::livewire.page-references-tab-placeholder');
    }

    public function render(ReferenceRows $references): View
    {
        // One more than shown tells whether "Show more" has anything to add.
        $pages = $references->pages($this->pageId, $this->limit + 1, $this->access->viewScope());

        return view('lynx-admin::livewire.page-references-tab', [
            'rows' => $references->rows($this->pageId, array_slice($pages, 0, $this->limit)),
            'hasMore' => count($pages) > $this->limit,
        ]);
    }
}
