<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Forms\Admin\OptionsConverter;
use LynxCms\Forms\Fields\OptionsType;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('formats', 'options');
    lynx()->templates->update('course', ['add' => ['formats']]);
    createCourses('alpha');
});

function normalizeOptions(mixed $value): mixed
{
    return app(OptionsType::class)->normalize($value, []);
}

it('keeps options with a stable value and a label per language', function (): void {
    expect(normalizeOptions([
        ['value' => 'film', 'label' => ['default' => 'Film', 'pt' => 'Filme']],
        ['label' => ['default' => 'Live stream', 'pt' => 'Transmissão']],
        ['value' => '', 'label' => ['default' => '', 'pt' => '']],
    ]))->toBe([
        ['value' => 'film', 'label' => ['default' => 'Film', 'pt' => 'Filme']],
        ['value' => 'live-stream', 'label' => ['default' => 'Live stream', 'pt' => 'Transmissão']],
    ]);
});

it('accepts a plain label for every language', function (): void {
    expect(normalizeOptions([['label' => 'Yes']]))->toBe([['value' => 'yes', 'label' => 'Yes']]);
});

it('refuses repeated values', function (): void {
    normalizeOptions([['value' => 'film', 'label' => 'Film'], ['label' => 'Film']]);
})->throws(ValidationException::class, 'film');

it('refuses values that are not slugs', function (): void {
    normalizeOptions([['value' => 'Two words', 'label' => 'Two words']]);
})->throws(ValidationException::class);

it('stores no value when every row is empty', function (): void {
    expect(normalizeOptions([['value' => '', 'label' => '']]))->toBeNull()->and(normalizeOptions(null))->toBeNull();
});

it('converts to and from the editor\'s form', function (): void {
    $converter = app(OptionsConverter::class);
    $stored = [['value' => 'film', 'label' => ['default' => 'Film', 'pt' => 'Filme']], ['value' => 'yes', 'label' => 'Yes']];
    $form = $converter->toForm($stored);

    expect($form)->toBe([
        ['value' => 'film', 'label' => ['default' => 'Film', 'pt' => 'Filme']],
        ['value' => 'yes', 'label' => ['default' => 'Yes', 'pt' => 'Yes']],
    ])->and($converter->fromForm($form))->toBe([
        ['value' => 'film', 'label' => ['default' => 'Film', 'pt' => 'Filme']],
        ['value' => 'yes', 'label' => ['default' => 'Yes', 'pt' => 'Yes']],
    ]);
});

it('edits options in the page editor', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    $id = (int) lynx()->pages->get('/courses/alpha/')?->id;

    Livewire::test(PageEditor::class, ['pageId' => $id])
        ->assertSeeHtml('data-options-editor')
        ->set('values.formats.default', [['value' => '', 'label' => ['default' => 'Teaser', 'pt' => 'Teaser']]])
        ->call('save')->assertHasNoErrors();

    expect(lynx()->pages->get('/courses/alpha/')?->getUnformatted('formats'))->toBe([['value' => 'teaser', 'label' => ['default' => 'Teaser', 'pt' => 'Teaser']]]);
});

/*
 * "options" holds a list that visitors pick from, unlike "choice", where an editor picks from a
 * list in the field's settings. Only the forms module creates options fields, from its schema, so
 * the field form leaves the type out of its menu; the API and existing fields keep it.
 */

it('says what an options field is for, unlike a choice field', function (): void {
    expect(lynx()->fields->types()['options']['description'])
        ->toStartWith('A list of choices that visitors pick from, e.g. the answers to a form question.')
        ->toContain('choice field');
});

it('leaves options out of the type menu for new fields', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(FieldForm::class)
        ->assertDontSeeHtml('<option value="options">')
        ->assertSeeHtml('<option value="choice">');
    Livewire::test(FieldForm::class)->set('name', 'answers')->set('label.default', 'Answers')->set('type', 'options')->call('save')
        ->assertHasErrors('type');
});

it('still shows an existing options field and creates them through the API', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(FieldForm::class, ['field' => 'formats'])->assertSee('options')->assertSee('visitors pick from');
    lynx()->fields->add('answers', 'options');

    expect(lynx()->fields->get('answers')->type)->toBe('options');
});
