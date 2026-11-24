<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire\Concerns;

use Livewire\Attributes\Locked;
use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\DeleteSubmissions;
use LynxCms\Forms\Actions\UpdateSubmissions;
use LynxCms\Forms\SubmissionFilter;

/**
 * The inbox's selection and bulk actions: rows ticked one by one or a page at a time, or every
 * submission the filters match (up to the newest one when it was chosen, so later ones are left
 * alone), then marked read, archived or spam, or deleted after a dry run says how many go.
 *
 * @internal
 */
trait ActsOnSubmissions
{
    /** @var list<string> uuids */
    public array $selected = [];

    /** Every submission the filters match is selected, up to $upTo. */
    #[Locked]
    public bool $allMatching = false;

    #[Locked]
    public ?int $upTo = null;

    /**
     * What deleting the selection removes, from a dry run, while it waits to be confirmed.
     *
     * @var array{submissions: int, files: int}|null
     */
    #[Locked]
    public ?array $confirmingDelete = null;

    abstract protected function filter(): SubmissionFilter;

    /** @return list<string> the uuids on the page shown */
    abstract protected function pageUuids(): array;

    /** Ticks every row on the page, or unticks them when they all are. */
    public function togglePage(): void
    {
        $uuids = $this->pageUuids();
        $isPageSelected = $uuids !== [] && array_diff($uuids, $this->selected) === [];
        $this->allMatching = false;
        $this->selected = $isPageSelected ? array_values(array_diff($this->selected, $uuids)) : array_values(array_unique([...$this->selected, ...$uuids]));
    }

    public function selectAllMatching(): void
    {
        $this->allMatching = true;
        $this->upTo = Value::int($this->filter()->query()->max('id'));
    }

    public function clearSelection(): void
    {
        $this->reset('selected', 'allMatching', 'upTo', 'confirmingDelete');
    }

    public function markSelected(string $status): void
    {
        $count = Value::int(lynx()->run(UpdateSubmissions::class, [...$this->selection(), 'status' => $status])->value);
        $this->clearSelection();
        $this->notify(trans_choice('lynx-forms::admin.inbox.updated', $count));
    }

    public function confirmDelete(): void
    {
        abort_unless(app(AdminAccess::class)->allows('forms.manage'), 403);
        $counts = Value::map(lynx()->run(DeleteSubmissions::class, $this->selection(), dryRun: true)->value);
        $this->confirmingDelete = ['submissions' => Value::int($counts['submissions'] ?? 0), 'files' => Value::int($counts['files'] ?? 0)];
    }

    public function deleteSelected(): void
    {
        $count = Value::int(Value::map(lynx()->run(DeleteSubmissions::class, $this->selection())->value)['submissions'] ?? 0);
        $this->clearSelection();
        $this->notify(trans_choice('lynx-forms::admin.inbox.deleted', $count));
    }

    public function selectionCount(): int
    {
        return $this->allMatching ? $this->filter()->withUpTo($this->upTo)->query()->count() : count($this->selected);
    }

    /** @return array<string, mixed> the selection as the bulk actions take it */
    private function selection(): array
    {
        return $this->allMatching
            ? ['filter' => ['show' => $this->filter()->show, ...$this->filter()->withUpTo($this->upTo)->toArray()]]
            : ['submissions' => $this->selected];
    }
}
