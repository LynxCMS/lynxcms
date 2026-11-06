<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Livewire\Attributes\Locked;
use LynxCms\Admin\Editor\ChangeReview;
use LynxCms\Admin\Editor\EditorForm;
use LynxCms\Admin\Editor\ReviewEntry;
use LynxCms\Core\Actions\Pages\SaveDraft;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Revisions;

/**
 * The page editor's Review changes: runs the save as a dry run, so errors show by their fields,
 * and otherwise lists each change before and after (ChangeReview). With a draft, it lists what the
 * draft, with any edit not saved yet, would change on the live page: what publishing would do.
 * Any edit closes the review.
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

        if ($this->hasDraft) {
            $this->reviewDraft($changes);

            return;
        }

        if ($changes === []) {
            $this->notify(__('lynx-admin::editor.no_changes_review'), 'warning');

            return;
        }

        lynx()->run(SavePage::class, ['id' => $this->pageId, 'values' => $changes, 'expected_revision' => $this->revision], dryRun: true);
        $this->isReviewing = true;
    }

    /** @param  array<string, mixed>  $unsaved */
    private function reviewDraft(array $unsaved): void
    {
        if ($unsaved !== []) {
            lynx()->run(SaveDraft::class, ['page' => $this->pageId, 'values' => $unsaved], dryRun: true);
        }

        if ($this->draftEntries($this->page(), $unsaved) === []) {
            $this->notify(__('lynx-admin::editor.no_draft_changes'), 'warning');

            return;
        }

        $this->isReviewing = true;
    }

    /**
     * What the draft, with the edits not saved yet, changes on the live page, field by field.
     *
     * @param  array<string, mixed>  $unsaved
     * @return list<ReviewEntry>
     */
    private function draftEntries(Page $live, array $unsaved): array
    {
        $draft = lynx()->pages->draft($live);

        if (! $draft instanceof Page) {
            return [];
        }

        $input = app(Revisions::class)->saveInput($live, $draft->valuesByLanguage());

        foreach ($unsaved as $name => $value) {
            $isTranslatable = $live->templateField((string) $name)?->field->isTranslatable() ?? false;
            $input[$name] = $isTranslatable && is_array($value) && is_array($input[$name] ?? null) ? array_replace($input[$name], $value) : $value;
        }

        $entries = app(ChangeReview::class)->forSave($live, $input);

        return array_values(array_filter($entries, fn (ReviewEntry $entry): bool => $entry->before !== $entry->after));
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
        if (! $this->isReviewing) {
            return [];
        }

        $changes = $this->changes(app(EditorForm::class));

        return $this->hasDraft ? $this->draftEntries($page, $changes) : app(ChangeReview::class)->forSave($page, $changes);
    }
}
