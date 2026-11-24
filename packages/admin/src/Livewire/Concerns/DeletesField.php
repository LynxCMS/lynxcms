<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Livewire\Attributes\Locked;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\Change;

/**
 * Deleting a field from its Advanced tab: a dry run first, so the confirmation says which
 * templates and values go, then fields.delete.
 *
 * @internal
 */
trait DeletesField
{
    /** Showing what deleting the field would remove, waiting for confirmation. */
    #[Locked]
    public bool $isConfirmingDelete = false;

    /** @var list<string> the dry run's change lines */
    #[Locked]
    public array $deleteSummary = [];

    public function confirmDelete(): void
    {
        $this->authorizeEditing();
        $result = lynx()->fields->delete((string) $this->field, dryRun: true);
        $this->deleteSummary = $result instanceof ActionResult ? array_map(fn (Change $change): string => $change->summary, $result->changes->all()) : [];
        $this->isConfirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->isConfirmingDelete = false;
        $this->deleteSummary = [];
    }

    public function delete(): void
    {
        $this->authorizeEditing();
        lynx()->fields->delete((string) $this->field);
        $this->redirectRoute('lynx.admin.fields');
    }

    /** Refuses people who may not change this field (the component's check). */
    abstract private function authorizeEditing(): void;
}
