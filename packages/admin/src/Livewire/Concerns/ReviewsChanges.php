<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Livewire\Attributes\Locked;
use LynxCms\Admin\Editor\ChangeReview;
use LynxCms\Admin\Editor\EditorForm;
use LynxCms\Admin\Editor\ReviewEntry;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;

/**
 * The page editor's Review changes: runs the save as a dry run, so errors show by their fields,
 * and otherwise lists each change before and after (ChangeReview). Any edit closes the review.
 *
 * @internal
 */
trait ReviewsChanges
{
    /** Showing what Save would change; any edit closes it, so it never shows stale values. */
    #[Locked]
    public bool $isReviewing = false;

    /** A dry run of the save: errors show by their fields, otherwise the changes are listed for review. */
    public function review(EditorForm $form): void
    {
        $this->status = null;
        $changes = $this->changes($form);

        if ($changes === []) {
            $this->notify('No changes to review.', 'warning');

            return;
        }

        lynx()->run(SavePage::class, ['id' => $this->pageId, 'values' => $changes, 'expected_revision' => $this->revision], dryRun: true);
        $this->isReviewing = true;
    }

    public function closeReview(): void
    {
        $this->isReviewing = false;
    }

    /** Livewire hook: an edited value makes the review out of date. */
    public function updatedValues(): void
    {
        $this->isReviewing = false;
    }

    /**
     * The changes under review, for the view.
     *
     * @return list<ReviewEntry>
     */
    private function reviewedChanges(Page $page): array
    {
        return $this->isReviewing ? app(ChangeReview::class)->forSave($page, $this->changes(app(EditorForm::class))) : [];
    }
}
