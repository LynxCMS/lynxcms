<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\TemplateForm;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('edits a field label, description and notes per language', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'summary'])
        ->assertSet('label', ['default' => 'Summary', 'pt' => ''])
        ->set('label.pt', 'Resumo')
        ->set('description.default', 'One paragraph')
        ->set('notes.default', 'Keep it short')->set('notes.pt', 'Curto')
        ->call('save')->assertHasNoErrors();

    $field = lynx()->fields->get('summary');
    expect($field->labels()->toStorage())->toBe(['default' => 'Summary', 'pt' => 'Resumo'])
        ->and($field->config()['description'])->toBe('One paragraph')
        ->and($field->config()['notes'])->toBe(['default' => 'Keep it short', 'pt' => 'Curto']);
});

it('stores text given only in the default language as plain text', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'summary'])->set('label.default', 'Abstract')->call('save');

    expect(lynx()->fields->get('summary')->labels()->toStorage())->toBe('Abstract');
});

it('edits a template label and its field overrides per language', function (): void {
    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->set('label.pt', 'Curso')->set('label.default', 'Course')->call('saveLabel')
        ->set('rows.price.label.pt', 'Preço')->call('saveFields')->assertHasNoErrors();

    expect(lynx()->templates->get('course')->labels()->toStorage())->toBe(['default' => 'Course', 'pt' => 'Curso'])
        ->and(lynx()->language('pt', fn () => lynx()->templates->fields('course')->get('price')?->label()))->toBe('Preço');
});

it('shows a field\'s notes in the page editor', function (): void {
    lynx()->fields->update('price', ['settings' => ['notes' => 'In euros']]);

    Livewire::test(PageEditor::class, ['pageId' => (int) lynx()->pages->get('/courses/alpha/')?->id])->assertSee('In euros');
});
