<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Editor\FieldInputs;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\PagePicker;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Page;
use LynxCms\Tests\Fixtures\ColourType;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('featured', 'toggle');
    lynx()->templates->update('course', ['add' => ['featured']]);
    $this->actingAs(new GenericUser(['id' => 1]));
});

function editable(): Page
{
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'delivery-8';
    $page->title = 'Navigating Project Delivery';
    $page->summary = 'English summary';
    $page->price = 249;
    $page->setLanguageValue('pt', 'title', 'Navegando');
    $page->save();

    return lynx()->pages->get($page->id);
}

it('renders an input per editable field with the stored values', function (): void {
    $page = editable();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSet('values.title.default', 'Navigating Project Delivery')
        ->assertSet('values.title.pt', 'Navegando')
        ->assertSet('values.summary.pt', '')
        ->assertSet('values.price.default', '249')
        ->assertSet('values.featured.default', false)
        ->assertSeeHtml('wire:model="values.title.pt"')
        ->assertSeeHtml('wire:model="values.price.default"')
        ->assertSeeHtml('type="checkbox"');
});

it('shows the fallback as the placeholder of an empty translation', function (): void {
    $page = editable();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('placeholder="English summary"');
});

it('saves values in two languages through pages.save', function (): void {
    $page = editable();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->set('values.title.pt', 'Navegando pela Entrega de Projetos')
        ->set('values.summary.pt', 'Resumo em português')
        ->set('values.price.default', '199')
        ->set('values.featured.default', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('lynx-notify', message: 'Saved.');

    $saved = lynx()->pages->get($page->id);
    expect(lynx()->language('pt', fn (): string => (string) $saved->title))->toBe('Navegando pela Entrega de Projetos')
        ->and(lynx()->language('pt', fn (): string => (string) $saved->summary))->toBe('Resumo em português')
        ->and($saved->price)->toBe(199)
        ->and($saved->featured)->toBeTrue();
});

it('clears a translation so it falls back again', function (): void {
    $page = editable();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->set('values.title.pt', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->getLanguageValue('pt', 'title'))->toBeNull();
});

it('sends only the fields that changed', function (): void {
    $page = editable();
    $sent = null;
    lynx()->hook(SavePage::class)->before(function (Input $input) use (&$sent): void {
        $sent = $input->array('values');
    });

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->set('values.price.default', '300')
        ->call('save');

    expect($sent)->toBe(['price' => 300]);
});

it('does nothing when nothing changed', function (): void {
    $page = editable();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->call('save')
        ->assertDispatched('lynx-notify', message: 'No changes to save.', type: 'warning');
});

it('shows core validation errors next to the field', function (): void {
    $page = editable();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->set('values.title.default', '')
        ->call('save')
        ->assertHasErrors('values.title')
        ->assertSee('Title is required in the default language.');
});

it('rejects a value that is not a number for an integer field', function (): void {
    $page = editable();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->set('values.price.default', 'cheap')
        ->call('save')
        ->assertHasErrors('values.price');
});

it('keeps a locked page read only', function (): void {
    $page = editable();
    lynx()->pages->setStatus($page, add: ['locked']);

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertDontSeeHtml('wire:click="save"')
        ->assertSeeHtml('disabled')
        ->set('values.price.default', '1')
        ->call('save')
        ->assertHasErrors('page');

    expect(lynx()->pages->get($page->id)->price)->toBe(249);
});

it('lets modules register their own input for a field type', function (): void {
    view()->addNamespace('test-inputs', __DIR__.'/views/inputs');
    app(FieldInputs::class)->register('integer', 'test-inputs::stars');
    $page = editable();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSee('stars input for price');
});

it('shows field types without an input as read-only values', function (): void {
    lynx()->fieldTypes()->register(ColourType::class);
    lynx()->fields->add('colour', 'colour');
    lynx()->templates->update('course', ['add' => ['colour']]);
    $page = editable();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSee('Colour')
        ->assertDontSeeHtml('values.colour');
});

it('refuses to overwrite a save made after the page was opened, keeping the edits', function (): void {
    $page = editable();
    $first = Livewire::test(PageEditor::class, ['pageId' => $page->id]);
    $second = Livewire::test(PageEditor::class, ['pageId' => $page->id]);

    $first->set('values.price.default', '300')->call('save')->assertHasNoErrors();

    $second->set('values.price.default', '400')
        ->call('save')
        ->assertHasErrors('conflict')
        ->assertSee('after you loaded it')
        ->assertSeeHtml('wire:click="reload"')
        ->assertSet('values.price.default', '400');

    expect(lynx()->pages->get($page->id)->price)->toBe(300);
});

it('reloads the latest version and saves again after a conflict', function (): void {
    $page = editable();
    $mine = Livewire::test(PageEditor::class, ['pageId' => $page->id]);
    saveSomewhereElse($page, 300);

    $mine->set('values.price.default', '400')->call('save')->assertHasErrors('conflict')
        ->call('reload')
        ->assertHasNoErrors()
        ->assertSet('values.price.default', '300')
        ->set('values.price.default', '400')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->price)->toBe(400);
});

it('keeps saving after its own saves without a conflict', function (): void {
    $page = editable();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->set('values.price.default', '1')->call('save')->assertHasNoErrors()
        ->set('values.price.default', '2')->call('save')->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->price)->toBe(2);
});

function saveSomewhereElse(Page $page, int $price): void
{
    lynx()->run(SavePage::class, ['id' => $page->id, 'values' => ['price' => $price]]);
}

it('edits page references with the page picker', function (): void {
    $page = editable();
    $alpha = createCourses('alpha')['alpha'];

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeLivewire(PagePicker::class)
        ->set('values.related.default', [(int) $alpha->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->related->ids())->toBe([(int) $alpha->id]);
});

it('edits a component field with a settings form from its schema', function (): void {
    lynx()->fields->add('enrol', 'component', settings: ['allowed' => ['enrol-form']]);
    lynx()->templates->update('course', ['add' => ['enrol']]);
    $page = editable();

    $editor = Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('value="enrol-form"')
        ->set('values.enrol.default.component', 'enrol-form')
        ->assertSeeHtml('wire:model="values.enrol.default.settings.capacity"')
        ->assertSeeHtml('type="number"')
        ->set('values.enrol.default.settings.capacity', '25')
        ->call('save')
        ->assertHasNoErrors();

    // The number input sends a string; the settings form casts it to the schema's integer.
    expect(lynx()->pages->get($page->id)->getLanguageValue('default', 'enrol'))
        ->toBe(['component' => 'enrol-form', 'settings' => ['capacity' => 25]]);

    $editor->set('values.enrol.default.component', '')->call('save')->assertHasNoErrors();
    expect(lynx()->pages->get($page->id)->getLanguageValue('default', 'enrol'))->toBeNull();
});

it('shows component validation errors under the field', function (): void {
    lynx()->fields->add('enrol', 'component', settings: ['allowed' => ['enrol-form']]);
    lynx()->templates->update('course', ['add' => ['enrol']]);
    $page = editable();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->set('values.enrol.default.component', 'enrol-form')
        ->set('values.enrol.default.settings.capacity', '5000')
        ->call('save')
        ->assertHasErrors('values.enrol');
});
