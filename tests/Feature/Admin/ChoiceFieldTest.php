<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Admin\Livewire\PageEditor;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function choiceEditorPage(): int
{
    lynx()->fields->add('level', 'choice', settings: ['input' => 'radios', 'options' => [
        ['value' => 'beginner', 'label' => ['default' => 'Beginner', 'pt' => 'Iniciante']],
        ['value' => 'advanced', 'label' => ['default' => 'Advanced', 'pt' => 'Avançado']],
    ]]);
    lynx()->fields->add('formats', 'choice', settings: ['multiple' => true, 'options' => [['value' => 'online', 'label' => 'Online'], ['value' => 'onsite', 'label' => 'On site']]]);
    lynx()->fields->add('size', 'choice', settings: ['options' => [['value' => 's', 'label' => 'Small'], ['value' => 'l', 'label' => 'Large']]]);
    lynx()->templates->update('course', ['add' => ['level', 'formats', 'size']]);
    ['alpha' => $alpha] = createCourses('alpha');

    return (int) $alpha->id;
}

it('defines the options of a choice field with the choices editor', function (): void {
    Livewire::test(FieldForm::class)
        ->set('name', 'level')->set('type', 'choice')
        ->assertSeeHtml('data-options-editor')
        ->set('settings.options', [
            ['value' => '', 'label' => ['default' => 'Beginner', 'pt' => 'Iniciante']],
            ['value' => 'advanced', 'label' => ['default' => 'Advanced', 'pt' => '']],
        ])
        ->set('settings.input', 'radios')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('level')->config()['options'])->toBe([
        ['value' => 'beginner', 'label' => ['default' => 'Beginner', 'pt' => 'Iniciante']],
        ['value' => 'advanced', 'label' => ['default' => 'Advanced']],
    ]);

    Livewire::test(FieldForm::class, ['field' => 'level'])
        ->assertSet('settings.options.0.label.pt', 'Iniciante')
        ->assertSet('settings.options.1.value', 'advanced');
});

it('shows a select, radios or checkboxes as the field asks, and saves the choices', function (): void {
    $id = choiceEditorPage();

    Livewire::test(PageEditor::class, ['pageId' => $id])
        ->assertSeeHtml('type="radio"')
        ->assertSeeHtml('type="checkbox"')
        ->assertSeeHtml('<option value="l">Large</option>')
        ->set('values.level.default', 'advanced')
        ->set('values.formats.default', ['onsite', 'online'])
        ->set('values.size.default', 'l')
        ->call('save')
        ->assertHasNoErrors();

    $page = lynx()->pages->get($id);

    expect($page?->level?->value)->toBe('advanced')
        ->and($page?->formats?->values())->toBe(['online', 'onsite'])
        ->and($page?->size?->value)->toBe('l');
});

it('groups radios by their form path, so repeater items keep their own choice', function (): void {
    $pageId = choiceEditorPage();
    lynx()->fields->add('steps', 'repeater', settings: ['fields' => [['name' => 'level']]]);
    lynx()->templates->update('course', ['add' => ['steps']]);

    Livewire::test(PageEditor::class, ['pageId' => $pageId])
        ->call('addItem', 'steps')
        ->call('addItem', 'steps')
        ->assertSeeHtml('name="values.steps.default.0.values.level.default"')
        ->assertSeeHtml('name="values.steps.default.1.values.level.default"');
});

it('marks the default option in the choices editor', function (): void {
    Livewire::test(FieldForm::class)
        ->set('name', 'level')->set('type', 'choice')
        ->set('settings.options', [
            ['value' => '', 'label' => ['default' => 'Beginner', 'pt' => ''], 'isDefault' => false],
            ['value' => '', 'label' => ['default' => 'Advanced level', 'pt' => ''], 'isDefault' => true],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('level')->config()['default'])->toBe('advanced-level');

    Livewire::test(FieldForm::class, ['field' => 'level'])
        ->assertSet('settings.options.0.isDefault', false)
        ->assertSet('settings.options.1.isDefault', true)
        ->set('settings.options.1.isDefault', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('level')->config())->not->toHaveKey('default');
});

it('marks several defaults on a choice of several', function (): void {
    Livewire::test(FieldForm::class)
        ->set('name', 'formats')->set('type', 'choice')->set('settings.multiple', true)
        ->set('settings.options', [
            ['value' => 'online', 'label' => ['default' => 'Online', 'pt' => ''], 'isDefault' => true],
            ['value' => 'onsite', 'label' => ['default' => 'On site', 'pt' => ''], 'isDefault' => true],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('formats')->config()['default'])->toBe(['online', 'onsite']);
});

it('starts a new repeater item with the defaults', function (): void {
    lynx()->fields->add('level', 'choice', settings: ['default' => 'advanced', 'options' => [['value' => 'beginner', 'label' => 'Beginner'], ['value' => 'advanced', 'label' => 'Advanced']]]);
    lynx()->fields->add('sessions', 'repeater', settings: ['fields' => [['name' => 'level']]]);
    lynx()->templates->update('course', ['add' => ['sessions']]);
    ['alpha' => $alpha] = createCourses('alpha');

    Livewire::test(PageEditor::class, ['pageId' => $alpha->id])
        ->call('addItem', 'sessions')
        ->assertSet('values.sessions.default.0.values.level.default', 'advanced');
});

it('offers no empty option on a required select that has a default', function (): void {
    lynx()->fields->add('size', 'choice', settings: ['default' => 's', 'options' => [['value' => 's', 'label' => 'Small'], ['value' => 'l', 'label' => 'Large']]]);
    lynx()->templates->update('course', ['add' => [['name' => 'size', 'required' => true]]]);
    ['alpha' => $alpha] = createCourses('alpha');

    Livewire::test(PageEditor::class, ['pageId' => $alpha->id])->assertDontSeeHtml('<option value="">');

    lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], 'size']]);
    Livewire::test(PageEditor::class, ['pageId' => $alpha->id])->assertSeeHtml('<option value="">');
});
