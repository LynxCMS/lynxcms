<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures\Forms;

use LynxCms\Core\Support\LocalizedText;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Livewire\FormComponent;
use LynxCms\Forms\Models\Submission;

/** The quote form offering films only, keeping the submission it sent: for the component hooks' tests. */
class FilmOnlyQuote extends FormComponent
{
    public ?int $sentId = null;

    /** @return array<string, LocalizedText> */
    protected function fieldOptions(FormField $field): array
    {
        return $field->name === 'type' ? array_intersect_key($field->options, ['film' => true]) : $field->options;
    }

    protected function afterSent(Submission $submission): void
    {
        $this->sentId = (int) $submission->id;
    }
}
