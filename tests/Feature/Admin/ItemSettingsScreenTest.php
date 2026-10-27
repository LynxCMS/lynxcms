<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Core\Actions\Pages\SavePage;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('video_url', 'url');
    $this->actingAs(new GenericUser(['id' => 1]));
});

function newMatrix(): Testable
{
    return Livewire::test(FieldForm::class)
        ->set('name', 'blocks')
        ->set('type', 'matrix')
        ->call('addType')
        ->set('itemTypes.0.label.default', 'Text')
        ->call('addSubfield', 'types.0', 'body')
        ->call('addType')
        ->set('itemTypes.1.slug', 'video')
        ->set('itemTypes.1.label.default', 'Video')
        ->call('addSubfield', 'types.1', 'video_url')
        ->call('addSubfield', 'types.1', 'summary');
}

function savedMatrix(): void
{
    lynx()->fields->add('blocks', 'matrix', settings: ['types' => [
        ['slug' => 'text', 'label' => 'Text', 'fields' => [['name' => 'body']]],
        ['slug' => 'video', 'label' => ['default' => 'Video', 'pt' => 'Vídeo'], 'item_label' => '{video_url}', 'fields' => [
            ['name' => 'video_url', 'required' => true, 'width' => 50],
            ['name' => 'summary', 'label' => 'Caption'],
        ]],
    ]]);
}

it('creates a matrix with block types and their fields', function (): void {
    newMatrix()
        ->set('itemTypes.1.fields.0.required', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('lynx.admin.fields.edit', 'blocks'));

    expect(lynx()->fields->get('blocks')->config()['types'])->toBe([
        ['slug' => 'text', 'label' => 'Text', 'fields' => [['name' => 'body']]],
        ['slug' => 'video', 'label' => 'Video', 'fields' => [['name' => 'video_url', 'required' => true], ['name' => 'summary']]],
    ]);
});

it('saves an existing matrix unchanged when nothing is edited', function (): void {
    savedMatrix();
    $before = lynx()->fields->get('blocks')->config()['types'];

    Livewire::test(FieldForm::class, ['field' => 'blocks'])->call('save')->assertHasNoErrors();

    expect(lynx()->fields->get('blocks')->config()['types'])->toBe($before);
});

it('keeps the slugs of saved types', function (): void {
    savedMatrix();

    Livewire::test(FieldForm::class, ['field' => 'blocks'])
        ->assertSet('savedSlugs', fn (array $slugs): bool => array_values($slugs) === ['text', 'video'])
        ->set('itemTypes.0.slug', 'prose')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('blocks')->fieldType()->slugs(lynx()->fields->get('blocks')->config()))->toBe(['text', 'video']);
});

it('adds only existing fields that may be listed, once each', function (): void {
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'summary']]]);

    Livewire::test(FieldForm::class)
        ->set('type', 'matrix')
        ->call('addType')
        ->call('addSubfield', 'types.0', 'body')
        ->call('addSubfield', 'types.0', 'body')
        ->call('addSubfield', 'types.0', 'nope')
        ->call('addSubfield', 'types.0', 'credits')
        ->call('addSubfield', 'types.9', 'summary')
        ->assertSet('itemTypes.0.fields', fn (array $fields): bool => array_column($fields, 'name') === ['body']);
});

it('orders types and their fields by dragging', function (): void {
    savedMatrix();
    $form = Livewire::test(FieldForm::class, ['field' => 'blocks']);

    $form->call('sortType', $form->get('itemTypes.1.key'), 0)
        ->call('sortSubfield', 'types.0:summary', 0)
        ->call('save')
        ->assertHasNoErrors();

    $types = lynx()->fields->get('blocks')->config()['types'];
    expect(array_column($types, 'slug'))->toBe(['video', 'text'])
        ->and(array_column($types[0]['fields'], 'name'))->toBe(['summary', 'video_url']);
});

it('removes types and fields', function (): void {
    savedMatrix();
    $form = Livewire::test(FieldForm::class, ['field' => 'blocks']);

    $form->call('removeSubfield', 'types.1', 'summary')
        ->call('removeType', $form->get('itemTypes.0.key'))
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('blocks')->config()['types'])->toHaveCount(1)
        ->and(lynx()->fields->get('blocks')->config()['types'][0]['fields'])->toBe([['name' => 'video_url', 'required' => true, 'width' => 50]]);
});

it('creates a new field from a block type and adds it there', function (): void {
    Livewire::test(FieldForm::class)
        ->set('type', 'matrix')
        ->call('addType')
        ->call('startSubfield', 'types.0')
        ->set('newSubfield.name', 'quote')
        ->set('newSubfield.type', 'text')
        ->set('newSubfield.translatable', true)
        ->call('createSubfield')
        ->assertHasNoErrors()
        ->assertSet('itemTypes.0.fields', fn (array $fields): bool => array_column($fields, 'name') === ['quote'])
        ->assertDispatched('close-dialog-new-subfield');

    expect(lynx()->fields->get('quote')->isTranslatable())->toBeTrue();
});

it('shows the errors of a new field in its dialog, not on the matrix', function (): void {
    Livewire::test(FieldForm::class)
        ->set('type', 'matrix')
        ->call('addType')
        ->call('startSubfield', 'types.0')
        ->set('newSubfield.name', 'Bad Name')
        ->call('createSubfield')
        ->assertHasErrors('newSubfield.name')
        ->assertHasNoErrors('name')
        ->assertSet('itemTypes.0.fields', []);

    Livewire::test(FieldForm::class)
        ->set('type', 'matrix')
        ->call('addType')
        ->call('startSubfield', 'types.0')
        ->set('newSubfield.name', 'nested')
        ->set('newSubfield.type', 'repeater')
        ->call('createSubfield')
        ->assertHasErrors('newSubfield.type');

    expect(lynx()->fields->find('nested'))->toBeNull();
});

it('shows the action\'s errors by the type they belong to', function (): void {
    newMatrix()->set('itemTypes.1.slug', 'text')->call('save')->assertHasErrors('settings.types.1.slug');
    Livewire::test(FieldForm::class)->set('name', 'blocks')->set('type', 'matrix')->call('save')->assertHasErrors('settings.types');
});

it('counts the pages that use each block type', function (): void {
    savedMatrix();
    lynx()->templates->update('course', ['add' => ['blocks']]);
    ['alpha' => $alpha] = createCourses('alpha', 'beta');
    lynx()->run(SavePage::class, ['id' => $alpha->id, 'values' => ['blocks' => [['type' => 'video', 'values' => ['video_url' => 'https://vimeo.com/1']]]]]);

    Livewire::test(FieldForm::class, ['field' => 'blocks'])
        ->assertViewHas('typeUsage', ['text' => 0, 'video' => 1]);
});

it('builds the fields of a repeater with the same list', function (): void {
    Livewire::test(FieldForm::class)
        ->set('name', 'credits')
        ->set('type', 'repeater')
        ->call('addSubfield', 'fields', 'related')
        ->call('addSubfield', 'fields', 'summary')
        ->set('itemFields.1.label.pt', 'Papel')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->fields->get('credits')->config()['fields'])->toBe([['name' => 'related'], ['name' => 'summary', 'label' => ['pt' => 'Papel']]]);

    Livewire::test(FieldForm::class, ['field' => 'credits'])->call('save')->assertHasNoErrors();
    expect(lynx()->fields->get('credits')->config()['fields'])->toBe([['name' => 'related'], ['name' => 'summary', 'label' => ['pt' => 'Papel']]]);
});

it('leaves no Blade directive uncompiled in the builder\'s buttons', function (): void {
    savedMatrix();

    $this->get(route('lynx.admin.fields.edit', 'blocks'))->assertOk()->assertDontSee('@js(', false);
    $this->get(route('lynx.admin.templates.edit', 'course'))->assertOk()->assertDontSee('@js(', false);
});

it('offers the site\'s own fields, not the ones modules keep for themselves', function (): void {
    app(SchemaOwners::class)->register('demo', fields: ['price']);

    Livewire::test(FieldForm::class)->set('type', 'matrix')
        ->assertViewHas('subfieldChoices', fn (array $choices): bool => isset($choices['body']) && ! isset($choices['price']));
});
