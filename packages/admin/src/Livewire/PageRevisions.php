<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\ActorNames;
use LynxCms\Admin\Editor\ChangeReview;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Actions\Pages\RestoreRevision;
use LynxCms\Core\Pages\Page;

/**
 * The Revisions section of the editor's Settings tab (ADR-0038): the page's saved versions,
 * each comparable with the page in the review table and restorable as a new save.
 *
 * @api
 */
final class PageRevisions extends Component
{
    use Notifies;

    #[Locked]
    public int $pageId;

    /** The revision being compared, or null. */
    #[Locked]
    public ?int $comparing = null;

    #[Locked]
    public ?string $status = null;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function mount(int $pageId): void
    {
        $this->pageId = $pageId;
    }

    public function compare(int $revision): void
    {
        $this->comparing = $this->comparing === $revision ? null : $revision;
        $this->status = null;
    }

    public function restore(int $revision): void
    {
        lynx()->run(RestoreRevision::class, ['page' => $this->pageId, 'revision' => $revision]);
        $this->comparing = null;
        $this->notify('Revision restored.');
        $this->dispatch('lynx-page-changed');
    }

    /** A save or a rename elsewhere in the editor adds a revision: rendering again shows it. */
    #[On('lynx-page-changed')]
    public function refresh(): void {}

    public function render(): View
    {
        $page = lynx()->pages->get($this->pageId) ?? abort(404);
        $names = app(ActorNames::class);
        $revisions = array_map(function (array $revision) use ($names): array {
            $parts = explode(':', (string) $revision['created_by'], 2);

            return $revision + ['by' => $names->label($parts[0], $parts[1] ?? null)];
        }, lynx()->pages->revisions($page));

        return view('lynx-admin::livewire.page-revisions', [
            'revisions' => $revisions,
            'review' => $this->comparing === null ? [] : $this->review($page, $this->comparing),
            'canRestore' => app(AdminAccess::class)->allows('pages.edit', $page),
        ]);
    }

    /**
     * What restoring the revision would change on the page now.
     *
     * @return list<mixed>
     */
    private function review(Page $page, int $revision): array
    {
        return app(ChangeReview::class)->forSave($page, lynx()->pages->revisionInput($page, $revision));
    }
}
