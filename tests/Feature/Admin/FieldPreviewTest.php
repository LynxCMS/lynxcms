<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldForm;

/*
 * The field form's Preview tab draws the control the page editor uses for the field: one checkbox
 * for a toggle, and for choice and page fields the select, radios, checkboxes or search their
 * "input" setting names, with their options.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('previews a toggle as one checkbox', function (): void {
    lynx()->fields->create('featured', 'toggle');

    Livewire::test(FieldForm::class, ['field' => 'featured'])
        ->assertSeeHtml('data-preview-input="checkbox"');
});

it('previews a choice field with the input it names, and its options', function (string $input, bool $isMultiple, string $shown): void {
    lynx()->fields->create('level', 'choice', settings: ['input' => $input, 'multiple' => $isMultiple, 'options' => [
        ['value' => 'basic', 'label' => 'Basic'],
        ['value' => 'advanced', 'label' => 'Advanced'],
    ]]);

    Livewire::test(FieldForm::class, ['field' => 'level'])
        ->assertSeeHtml('data-preview-input="'.$shown.'"')
        ->assertSeeHtml('data-preview-option="basic"')
        ->assertSeeHtml('data-preview-option="advanced"');
})->with([
    'a select' => ['select', false, 'select'],
    'radios' => ['radios', false, 'radios'],
    'checkboxes' => ['checkboxes', true, 'checkboxes'],
]);

it('previews a page field as a search, or as checkboxes listing its pages', function (): void {
    createCourses('alpha', 'beta');
    lynx()->fields->create('topics', 'page', settings: ['multiple' => true, 'templates' => ['course']]);

    Livewire::test(FieldForm::class, ['field' => 'topics'])->assertSeeHtml('data-preview-input="search"');

    lynx()->fields->update('topics', ['settings' => ['input' => 'checkboxes']]);

    Livewire::test(FieldForm::class, ['field' => 'topics'])
        ->assertSeeHtml('data-preview-input="checkboxes"')
        ->assertSeeHtml('data-preview-option="'.lynx()->pages->get('/courses/alpha/')?->id.'"');
});
