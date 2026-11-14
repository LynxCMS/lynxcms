<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use LynxCms\Core\Models\Field;

/**
 * "Make translatable" on a shared field's screen (fields.make_translatable, ADR-0118). It is
 * offered with one language too, so a field made shared by mistake is ready before a second
 * language arrives. There is no way back, which the button's confirmation says.
 *
 * @internal
 */
trait MakesFieldTranslatable
{
    public function makeTranslatable(): void
    {
        $this->authorizeEditing();

        if (lynx()->fields->makeTranslatable((string) $this->field) instanceof Field) {
            $this->translatable = true;
            $this->notify(__('lynx-admin::fields.languages.made_translatable'));
        }
    }

    private function canMakeTranslatable(): bool
    {
        return $this->field !== null && ! $this->translatable && $this->typeCanTranslate() && $this->canEdit();
    }
}
