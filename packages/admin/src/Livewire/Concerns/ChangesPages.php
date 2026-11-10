<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use LynxCms\Core\Actions\Pages\TrashPage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Changing several pages at once: publish, unpublish, hide, show or trash the selected ones (the
 * trash after a confirmation). Ids come from the browser, so the component checks each one
 * (changeablePage()); the pages actions check the user's permissions. A bulk change goes on past a
 * page it may not change and says so. Used by the Children tab and the Page Finder.
 *
 * @internal
 */
trait ChangesPages
{
    /** Bulk action => [flag, add it (true) or remove it (false)]. */
    private const array BULK = ['publish' => ['unpublished', false], 'unpublish' => ['unpublished', true], 'hide' => ['hidden', true], 'show' => ['hidden', false]];

    /** @var list<int> the ticked pages */
    public array $selected = [];

    /** @var list<int> pages waiting for the trash to be confirmed */
    #[Locked]
    public array $trashing = [];

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
        $this->trashing = array_values(array_map(fn (Page $page): int => (int) $page->id, array_map($this->changeablePage(...), Value::ints($ids))));
        $this->trashConfirming();
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

    /** A page the component may change, by an id the browser sent; throws ValidationException otherwise. */
    abstract private function changeablePage(int $id): Page;

    /** Called when the trash waits for its confirmation, to close what else was open. */
    abstract private function trashConfirming(): void;

    /**
     * Runs the change on every selected page, counting the ones it changed and the ones refused,
     * and keeping the first refusal's reason to show.
     *
     * @param  callable(Page): mixed  $change
     * @return array{done: int, refused: int, reason: string}
     */
    private function each(callable $change): array
    {
        $counts = ['done' => 0, 'refused' => 0, 'reason' => ''];

        foreach (Value::ints($this->selected) as $id) {
            try {
                $change($this->changeablePage($id));
                $counts['done']++;
            } catch (ValidationException|AuthorizationException $refusal) {
                // Refused for this page (its status, a lock, the user's scope): the others go on.
                $counts['refused']++;
                $counts['reason'] = $counts['reason'] ?: $this->reason($refusal);
            }
        }

        $this->selected = [];

        return $counts;
    }

    /**
     * @param  array{done: int, refused: int, reason: string}  $counts
     */
    private function report(array $counts): void
    {
        $message = trans_choice('lynx-admin::children.changed', $counts['done']);

        if ($counts['refused'] > 0) {
            $this->notify(trim($message.' '.trans_choice('lynx-admin::children.refused', $counts['refused']).' '.$counts['reason']), 'warning');

            return;
        }

        $this->notify($message);
    }

    private function reason(ValidationException|AuthorizationException $refusal): string
    {
        return $refusal instanceof ValidationException ? (string) $refusal->validator->errors()->first() : $refusal->getMessage();
    }
}
