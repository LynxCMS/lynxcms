<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Livewire\Attributes\Locked;
use LynxCms\Admin\Editor\EditorForm;
use LynxCms\Core\Actions\Pages\DiscardDraft;
use LynxCms\Core\Actions\Pages\PublishDraft;
use LynxCms\Core\Actions\Pages\SaveDraft;

/**
 * The page editor's draft mode (ADR-0039): changes go to the page's draft instead of the live
 * page until the draft is published or discarded. While a draft exists the form shows its values.
 *
 * @internal
 */
trait EditsDraft
{
    #[Locked]
    public bool $hasDraft = false;

    public function saveDraft(EditorForm $form): void
    {
        $this->status = null;
        $changes = $this->changes($form);

        if ($changes === []) {
            $this->notify(__('lynx-admin::editor.no_changes'), 'warning');

            return;
        }

        lynx()->run(SaveDraft::class, ['page' => $this->pageId, 'values' => $changes]);
        $this->load();
        $this->notify(__('lynx-admin::editor.draft_saved'));
    }

    /** Pending edits go into the draft first, then the draft goes live. */
    public function publishDraft(EditorForm $form): void
    {
        if ($this->changes($form) !== []) {
            $this->saveDraft($form);
        }

        lynx()->run(PublishDraft::class, ['page' => $this->pageId]);
        $this->load();
        $this->notify(__('lynx-admin::editor.draft_published'));
        $this->dispatch('lynx-page-changed');
    }

    public function discardDraft(): void
    {
        lynx()->run(DiscardDraft::class, ['page' => $this->pageId]);
        $this->load();
        $this->notify(__('lynx-admin::editor.draft_discarded'));
    }
}
