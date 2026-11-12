<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Livewire\Attributes\Locked;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Editor\ChildList;
use LynxCms\Admin\Editor\EditorForm;
use LynxCms\Admin\Editor\FollowUp;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

/**
 * The page editor's publish button. Publishing makes the form what visitors see: a live page's
 * changes are saved, an unpublished page is saved and goes live in one save, and a waiting draft
 * is published with the edits not saved yet. Without pages.publish the button saves instead. Its
 * menu publishes, then leaves the editor (FollowUp). The Save button beside it (save(), and
 * Cmd/Ctrl+S) never publishes.
 *
 * @internal
 */
trait PublishesPage
{
    /** The page after this one in the tree, for "publish and edit next"; found once on mount. */
    #[Locked]
    public ?int $nextPageId = null;

    /** Publishes, or saves without pages.publish, then goes where $then says, if anywhere. */
    public function publish(EditorForm $form, string $then = ''): void
    {
        $this->status = null;
        $followUp = FollowUp::tryFrom($then);
        $followUp = in_array($followUp, $this->followUps($this->page()), true) ? $followUp : null;

        // With somewhere to go and nothing to publish, go without a "no changes" notice.
        if ($followUp === null || $this->hasWorkToPublish($form)) {
            $this->canPublish() ? $this->publishPage($form) : $this->save($form);
        }

        if ($followUp instanceof FollowUp) {
            $this->leaveTo($followUp);
        }
    }

    /**
     * The menu's follow-ups for this page: always back to the tree; to the page on the site when
     * it has a URL; a new page beside it when the parent takes one the user may create; the next page.
     *
     * @return list<FollowUp>
     */
    protected function followUps(Page $page): array
    {
        $parent = $page->parent();

        return array_values(array_filter([
            FollowUp::Exit,
            $page->template()->hasUrl() ? FollowUp::View : null,
            $parent instanceof Page && app(ChildList::class)->canAddChild($parent) ? FollowUp::Add : null,
            $this->nextPageId !== null ? FollowUp::Next : null,
        ]));
    }

    protected function canPublish(): bool
    {
        return app(AdminAccess::class)->allows('pages.publish', $this->page());
    }

    private function hasWorkToPublish(EditorForm $form): bool
    {
        return $this->changes($form) !== []
            || ($this->canPublish() && ($this->hasDraft || $this->page()->hasStatus(PageStatus::UNPUBLISHED)));
    }

    private function publishPage(EditorForm $form): void
    {
        $hadDraft = $this->hasDraft;

        if ($hadDraft) {
            $this->publishDraft($form);
        }

        if ($this->page()->hasStatus(PageStatus::UNPUBLISHED)) {
            $this->goLive($form);
        } elseif (! $hadDraft) {
            // A live page without a draft: saving its fields publishes them.
            $this->saveFields($form);
        }
    }

    /** Saves the form and clears the unpublished flag in one pages.save, keeping the other flags. */
    private function goLive(EditorForm $form): void
    {
        $status = array_values(array_diff(PageStatus::toNames($this->page()->status), ['unpublished']));
        lynx()->run(SavePage::class, ['id' => $this->pageId, 'values' => $this->changes($form), 'status' => $status, 'expected_revision' => $this->revision]);
        $this->load();
        $this->notify(__('lynx-admin::editor.page_published'));
        $this->dispatch('lynx-page-changed', revision: $this->revision);
    }

    private function leaveTo(FollowUp $followUp): void
    {
        // The notice would be lost with the page; the next admin page shows it (components/notifications).
        if ($this->status !== null && $followUp !== FollowUp::View) {
            session()->flash('lynx-notify', ['message' => $this->status, 'type' => 'success']);
        }

        $page = $this->page();

        match ($followUp) {
            FollowUp::Exit => $this->redirectRoute('lynx.admin.pages', navigate: true),
            FollowUp::View => $this->redirect((string) $page->url()),
            FollowUp::Add => $this->redirectRoute('lynx.admin.pages.create', ['parent' => $page->parentId], navigate: true),
            FollowUp::Next => $this->redirectRoute('lynx.admin.pages.edit', ['page' => $this->nextPageId], navigate: true),
        };
    }
}
