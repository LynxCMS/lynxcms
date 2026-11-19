<?php

declare(strict_types=1);

namespace LynxCms\Forms\Livewire\Concerns;

use LynxCms\Core\Support\LocalizedText;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;

/**
 * Lets a form component offer fewer options in a choice than the form has, for the page it is on
 * (the languages a document exists in, say). The fields it gives are the ones shown and checked.
 *
 * @internal
 */
trait NarrowsChoices
{
    abstract public function definition(): Form;

    /**
     * A choice's options on this component: all of the form's, unless a subclass narrows them for
     * the page it is on. Shown, and checked before sending, so other answers are refused.
     *
     * @return array<string, LocalizedText> value => label
     */
    protected function fieldOptions(FormField $field): array
    {
        return $field->options;
    }

    /**
     * The form's fields, with each choice's options as fieldOptions() gives them.
     *
     * @return list<FormField>
     */
    protected function fields(): array
    {
        return array_map($this->withOptions(...), $this->definition()->fields());
    }

    private function withOptions(FormField $field): FormField
    {
        if (! in_array($field->type, FormField::CHOICE_TYPES, true)) {
            return $field;
        }

        $options = $this->fieldOptions($field);

        return $options === $field->options ? $field : $field->options(array_map(fn (LocalizedText $label): string|array => $label->toStorage(), $options));
    }
}
