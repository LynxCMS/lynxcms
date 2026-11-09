<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use LynxCms\Core\Actions\Pages\TrashPage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;

/**
 * Status changes and trash for the Children tab: one child at a time from its row, or the selected
 * ones at once. Ids come from the browser, so each must be a child of the page; the pages actions
 * check the user's permissions. A bulk change goes on past a page it may not change and says so.
 */
trait ChangesChildren
{
    /** Bulk action => [flag, add it (true) or remove it (false)]. */
    private const array BULK = ['publish' => ['unpublished', false], 'unpublish' => ['unpublished', true], 'hide' => ['hidden', true], 'show' => ['hidden', false]];

    /** @var list<int> the ticked children */
    public array $selected = [];

    /** @var list<int> children waiting for the trash to be confirmed */
    #[Locked]
    public array $trashing = [];

    public ?string $status = null;

    /** Flip a child's unpublished or hidden flag. */
    public function toggle(int $id, string $flag): void
    {
        if (! in_array($flag, ['unpublished', 'hidden'], true)) {
            return;
        }

        $page = $this->child($id);
        $has = $page->hasStatus(PageStatus::NAMES[$flag]);
        lynx()->pages->setStatus($page, ...($has ? ['remove' => [$flag]] : ['add' => [$flag]]));
    }

    public function bulk(string $action): void
    {
        if (! isset(self::BULK[$action])) {
            return;
        }

        [$flag, $add] = self::BULK[$action];
        $this->report($this->each(fn (Page $page): mixed => lynx()->pages->setStatus($page, ...($add ? ['add' => [$flag]] : ['remove' => [$flag]]))));
    }

    /**
     * @param  array<array-key, mixed>  $ids
     */
    public function confirmTrash(array $ids): void
    {
        $this->trashing = array_values(array_map(fn (Page $page): int => (int) $page->id, array_map($this->child(...), Value::ints($ids))));
    }

    public function cancelTrash(): void
    {
        $this->trashing = [];
    }

    public function trash(): void
    {
        $this->selected = $this->trashing;
        $this->trashing = [];
        $this->report($this->each(fn (Page $page): mixed => lynx()->run(TrashPage::class, ['page' => (int) $page->id])));
    }

    /**
     * Runs the change on every selected child, counting the ones it changed and the ones refused.
     *
     * @param  callable(Page): mixed  $change
     * @return array{done: int, refused: int}
     */
    private function each(callable $change): array
    {
        $counts = ['done' => 0, 'refused' => 0];

        foreach (Value::ints($this->selected) as $id) {
            try {
                $change($this->child($id));
                $counts['done']++;
            } catch (ValidationException|AuthorizationException) {
                // Refused for this page (its status, a lock, the user's scope): the others go on.
                $counts['refused']++;
            }
        }

        $this->selected = [];

        return $counts;
    }

    /**
     * @param  array{done: int, refused: int}  $counts
     */
    private function report(array $counts): void
    {
        $message = trans_choice('lynx-admin::children.changed', $counts['done']);

        if ($counts['refused'] > 0) {
            $this->notify($message.' '.trans_choice('lynx-admin::children.refused', $counts['refused']), 'warning');

            return;
        }

        $this->notify($message);
    }

    /** A child of the page, by an id the browser sent. */
    private function child(int $id): Page
    {
        $page = lynx()->pages->get($id);

        if (! $page instanceof Page || $page->parentId !== $this->pageId) {
            throw ValidationException::withMessages(['page' => __('lynx-admin::children.not_a_child')]);
        }

        return $page;
    }
}
