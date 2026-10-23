<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageCreator;
use LynxCms\Admin\Livewire\TemplateForm;
use LynxCms\Admin\Livewire\TemplateList;
use LynxCms\Core\Actions\Pages\TrashPage;
use LynxCms\Core\Templates\Layout\TemplateLayout;
use LynxCms\Core\Templates\TemplateField;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    $this->actingAs(new GenericUser(['id' => 1]));
});

/** @return list<string> */
function templateFieldNames(string $template): array
{
    return lynx()->templates->fields($template)->keys()->all();
}

it('lists templates with their fields and pages', function (): void {
    $this->get('/lynx/templates')->assertOk()->assertSeeLivewire(TemplateList::class)->assertSee('href="'.route('lynx.admin.templates').'"', false);

    Livewire::test(TemplateList::class)
        ->assertViewHas('rows', fn (array $rows): bool => $rows['course']['fields'] === 5 && $rows['course']['pages'] === 2 && $rows['courses']['pages'] === 1);
});

it('creates a template, optionally copying another one\'s fields', function (): void {
    Livewire::test(TemplateForm::class)
        ->set('name', 'workshop')->set('label.default', 'Workshop')->set('from', 'course')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect(route('lynx.admin.templates.edit', 'workshop'));

    expect(templateFieldNames('workshop'))->toBe(['title', 'summary', 'body', 'price', 'related'])
        ->and(lynx()->templates->get('workshop')->label)->toBe('Workshop');
});

it('shows the action\'s errors for a new template', function (): void {
    Livewire::test(TemplateForm::class)->set('name', 'course')->call('create')->assertHasErrors('name');
});

it('reorders, adds and removes fields, then saves them in one go', function (): void {
    lynx()->fields->add('venue', 'text');

    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->call('moveItem', 'field:price', 0, 'tab:content:main')
        ->set('newField', 'venue')->call('addField')
        ->call('removeField', 'related')
        ->assertSee('Values of removed fields are kept')
        ->call('saveFields')
        ->assertHasNoErrors()
        ->assertDispatched('lynx-notify', message: 'Saved.');

    expect(templateFieldNames('course'))->toBe(['price', 'title', 'summary', 'body', 'venue']);
});

it('offers only fields the template does not have yet', function (): void {
    lynx()->fields->add('venue', 'text');

    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->assertViewHas('available', fn (array $available): bool => in_array('venue', $available, true) && ! in_array('price', $available, true));
});

it('saves per template overrides of a field', function (): void {
    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->set('rows.price.label.default', 'Course fee')
        ->set('rows.price.required', true)
        ->set('rows.price.description.default', 'In euros, VAT included.')
        ->set('rows.price.show_if', 'title!=')
        ->call('saveFields')
        ->assertHasNoErrors();

    $price = lynx()->templates->fields('course')['price'];
    expect($price)->toBeInstanceOf(TemplateField::class)
        ->and([$price->label(), $price->required(), $price->settings['description'] ?? null, $price->settings['show_if'] ?? null])
        ->toBe(['Course fee', true, 'In euros, VAT included.', 'title!=']);
});

it('marks fields searchable per template, and shows which are', function (): void {
    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->set('rows.summary.searchable', true)
        ->call('saveFields')
        ->assertHasNoErrors()
        ->assertSeeHtml('id="override-summary-searchable"')
        ->assertSee('searchable');

    expect(lynx()->templates->fields('course')['summary']->searchable())->toBeTrue()
        ->and(lynx()->templates->fields('course')['body']->searchable())->toBeFalse();
});

it('shows a condition the template refuses', function (): void {
    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->set('rows.price.required_if', 'venue=onsite')
        ->call('saveFields')
        ->assertHasErrors('fields')
        ->assertSee('not on this template');
});

it('edits the label', function (): void {
    Livewire::test(TemplateForm::class, ['template' => 'course'])->set('label.default', 'Course page')->call('saveLabel')->assertHasNoErrors();

    expect(lynx()->templates->get('course')->label)->toBe('Course page');
});

it('answers 404 for a template that does not exist', function (): void {
    $this->get('/lynx/templates/nope/edit')->assertNotFound();
});

it('saves family settings: children, child and parent templates, URL and singleton', function (): void {
    lynx()->templates->add('lesson', fields: ['title']);

    Livewire::test(TemplateForm::class, ['template' => 'courses'])
        ->set('childTemplates', ['course'])
        ->set('hasUrl', true)
        ->call('saveFamily')->assertHasNoErrors()->assertDispatched('lynx-notify', message: 'Saved.');
    Livewire::test(TemplateForm::class, ['template' => 'lesson'])
        ->set('allowChildren', false)->set('parentTemplates', ['course'])->set('singleton', false)
        ->call('saveFamily')->assertHasNoErrors();

    expect(lynx()->templates->get('courses')->childTemplates())->toBe(['course'])
        ->and(lynx()->templates->get('lesson')->allowsChildren())->toBeFalse()
        ->and(lynx()->templates->get('lesson')->parentTemplates())->toBe(['course']);
});

it('offers new pages only the templates the parent allows', function (): void {
    lynx()->templates->add('lesson', fields: ['title']);
    lynx()->templates->update('courses', ['child_templates' => ['course']]);

    Livewire::test(PageCreator::class, ['parentId' => lynx()->pages->get('/courses/')->id])
        ->assertViewHas('choices', fn (array $choices): bool => array_keys($choices) === ['course']);

    lynx()->templates->update('course', ['allow_children' => false]);
    Livewire::test(PageCreator::class, ['parentId' => lynx()->pages->get('/courses/alpha/')->id])
        ->assertSee('No template can be used under Alpha.');
});

it('refuses to delete a template that pages use', function (): void {
    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->assertSee('2 pages use this template')
        ->call('confirmDelete')
        ->assertHasErrors('template')
        ->assertSee('is used by 2 pages');
});

it('lists the pages that use a template, linking to where their template is switched', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha/');

    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->assertSeeInOrder(['Alpha', 'Beta'])
        ->assertSee('/courses/alpha')
        ->assertSeeHtml(route('lynx.admin.pages.edit', ['page' => $alpha?->id, 'tab' => 'settings']));
});

it('marks trashed pages, which still keep the template', function (): void {
    lynx()->run(TrashPage::class, ['page' => lynx()->pages->get('/courses/beta/')?->id]);

    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->assertSee('2 pages use this template')
        ->assertSee('In the trash');
});

it('explains how to free a template instead of offering to delete it', function (): void {
    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->assertDontSee('Delete template')
        ->assertSee('Switch these pages to another template');
});

it('deletes an unused template after confirmation', function (): void {
    lynx()->templates->add('lesson', fields: ['title']);

    Livewire::test(TemplateForm::class, ['template' => 'lesson'])
        ->call('confirmDelete')->assertSet('isConfirmingDelete', true)->assertSee('Delete template lesson')
        ->call('delete')->assertRedirect(route('lynx.admin.templates'));

    expect(lynx()->templates->find('lesson'))->toBeNull();
});

it('keeps the template layout when the field list is saved', function (): void {
    lynx()->templates->update('course', [
        'tabs' => [['key' => 'extra', 'label' => 'Extra']],
        'fields' => [
            'title',
            ['section' => 'intro', 'label' => 'Introduction', 'collapsed' => true, 'fields' => ['summary', 'body']],
            ['name' => 'price', 'column' => 'sidebar'],
            ['name' => 'related', 'tab' => 'extra'],
        ],
    ]);

    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->set('rows.title.required', true)
        ->call('saveFields')
        ->assertHasNoErrors();

    $layout = TemplateLayout::for(lynx()->templates->get('course'));

    expect($layout->section('intro')?->collapsed)->toBeTrue()
        ->and($layout->blocks('content', 'sidebar')[0]->field)->toBe('price')
        ->and($layout->tabOf('related'))->toBe('extra')
        ->and(lynx()->templates->fields('course')->get('title')?->required())->toBeTrue();
});

it('saves how a field shows in the page editor', function (): void {
    Livewire::test(TemplateForm::class, ['template' => 'course'])
        ->set('rows.price.editor', 'locked')
        ->set('rows.price.width', '50')
        ->set('rows.price.placeholder.default', 'In euros')
        ->set('rows.price.icon', 'euro')
        ->set('rows.summary.editor', 'open')
        ->set('rows.summary.width', '100')
        ->call('saveFields')
        ->assertHasNoErrors()
        ->assertSee('locked');

    $fields = lynx()->templates->fields('course');
    expect([$fields['price']->editor()->value, $fields['price']->width(), $fields['price']->placeholder(), $fields['price']->icon()])->toBe(['locked', 50, 'In euros', 'euro'])
        ->and(array_intersect_key($fields['summary']->settings, array_flip(['editor', 'width'])))->toBe([]);
});
