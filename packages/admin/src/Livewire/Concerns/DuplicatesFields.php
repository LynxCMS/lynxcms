<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Livewire\Attributes\Locked;
use LynxCms\Core\Models\Field;

/**
 * Duplicate on the Fields screen (fields.duplicate): a dialog with the new name, a free one made
 * from the original's, and its label, then the new field opens on its own screen.
 *
 * @internal
 */
trait DuplicatesFields
{
    /** The field being duplicated; empty while the dialog is closed. */
    #[Locked]
    public string $duplicating = '';

    public string $duplicateName = '';

    public string $duplicateLabel = '';

    public function openDuplicate(string $field): void
    {
        $original = lynx()->fields->find($field);

        if (! $original instanceof Field) {
            return;
        }

        $this->duplicating = $field;
        $this->duplicateName = $this->freeName($field.'_copy');
        $this->duplicateLabel = (string) $original->label;
        $this->resetErrorBag();
        $this->dispatch('open-dialog-duplicate-field');
    }

    public function duplicate(): void
    {
        $original = lynx()->fields->find($this->duplicating);

        if (! $original instanceof Field) {
            return;
        }

        // An unchanged label keeps every language's label; a changed one replaces them.
        $label = trim($this->duplicateLabel) === '' || $this->duplicateLabel === (string) $original->label ? null : trim($this->duplicateLabel);
        $copy = lynx()->fields->duplicate($original, trim($this->duplicateName), label: $label);

        if ($copy instanceof Field) {
            $this->redirectRoute('lynx.admin.fields.edit', $copy->name, navigate: true);
        }
    }

    private function freeName(string $name): string
    {
        $candidate = $name;

        for ($number = 2; lynx()->fields->find($candidate) instanceof Field; $number++) {
            $candidate = $name.'_'.$number;
        }

        return $candidate;
    }
}
