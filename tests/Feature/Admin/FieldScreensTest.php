<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\FieldForm;
use LynxCms\Admin\Livewire\FieldList;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('lists fields with their type and the templates that use them', function (): void {
    $this->get('/lynx/fields')->assertOk()->assertSeeLivewire(FieldList::class);

    Livewire::test(FieldList::class)
        ->assertSee(['body', 'textarea, translatable'])
        ->assertViewHas('usedBy', fn (array $usedBy): bool => $usedBy['body'] === ['course'] && array_intersect(['course', 'courses'], $usedBy['title']) === ['course', 'courses']);
});

it('filters fields by type, template and text', function (): void {
    Livewire::test(FieldList::class)
        ->set('type', 'integer')->assertSee('price')->assertDontSee('summary')
        ->set('type', '')->set('template', 'courses')->assertSee('title')->assertDontSee('price')
        ->set('template', '')->set('search', 'sum')->assertSee('summary')->assertDontSee('price');
});

it('offers the top bar link', function (): void {
    $this->get('/lynx/fields')->assertSee('href="'.route('lynx.admin.fields').'"', false);
});

it('creates a field with settings from its type', function (): void {
    Livewire::test(FieldForm::class)
        ->set('name', 'intro')
        ->set('label.default', 'Introduction')
        ->set('type', 'textarea')
        ->assertSee('How the text is entered and rendered.')
        ->set('translatable', true)
        ->set('settings.format', 'html')
        ->set('settings.max_length', '500')
        ->set('description.default', 'Shown above the course body.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('lynx.admin.fields.edit', 'intro'));

    $field = lynx()->fields->get('intro');
    expect([$field->label, $field->type, $field->isTranslatable()])->toBe(['Introduction', 'textarea', true])
        ->and($field->config())->toMatchArray(['format' => 'html', 'max_length' => 500, 'description' => 'Shown above the course body.']);
});

it('shows the action\'s errors next to the inputs', function (): void {
    Livewire::test(FieldForm::class)->set('name', 'Bad Name')->set('type', 'text')->call('save')->assertHasErrors('name');
    Livewire::test(FieldForm::class)->set('name', 'price')->set('type', 'text')->call('save')->assertHasErrors('name');
    Livewire::test(FieldForm::class)->set('name', 'teaser')->set('type', 'text')->set('settings.max_length', '9999')->call('save')->assertHasErrors('settings.max_length');
});

it('takes lists as comma separated text', function (): void {
    Livewire::test(FieldForm::class)
        ->set('name', 'mentors')->set('type', 'page')
        ->set('settings.templates', 'course, courses')->set('settings.multiple', false)
        ->call('save')->assertHasNoErrors();

    expect(lynx()->fields->get('mentors')->config())->toMatchArray(['templates' => ['course', 'courses'], 'multiple' => false]);
});

it('edits label, description and settings, with name and type fixed', function (): void {
    $this->get(route('lynx.admin.fields.edit', 'summary'))->assertOk()->assertSeeLivewire(FieldForm::class);

    Livewire::test(FieldForm::class, ['field' => 'summary'])
        ->assertSet('name', 'summary')
        ->assertSee(['Used by', 'course'])
        ->set('label.default', 'Short summary')
        ->set('settings.max_length', '300')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('lynx-notify', message: 'Saved.');

    expect(lynx()->fields->get('summary')->label)->toBe('Short summary')
        ->and(lynx()->fields->get('summary')->config()['max_length'])->toBe(300);
});

it('turns loading with the page on and off', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'summary'])->assertSee('Load with the page')->set('autoload', true)->call('save')->assertHasNoErrors();

    expect(lynx()->fields->get('summary')->loadsWithPage())->toBeTrue();

    Livewire::test(FieldForm::class, ['field' => 'summary'])->assertSet('autoload', true)->set('autoload', false)->call('save');

    expect(lynx()->fields->get('summary')->config())->not->toHaveKey('autoload')
        ->and(lynx()->fields->get('title')->loadsWithPage())->toBeTrue();
    Livewire::test(FieldForm::class, ['field' => 'title'])->assertSee('Always loaded with the page.')->assertDontSee('Load with the page');
});

it('answers 404 for a field that does not exist', function (): void {
    $this->get('/lynx/fields/nope/edit')->assertNotFound();
});

it('previews what deleting a field removes, then deletes it on confirmation', function (): void {
    $form = Livewire::test(FieldForm::class, ['field' => 'price'])
        ->call('confirmDelete')
        ->assertSet('isConfirmingDelete', true)
        ->assertSee(['removes it from templates course', 'Delete field price']);

    expect(lynx()->fields->find('price'))->not->toBeNull();

    $form->call('delete')->assertRedirect(route('lynx.admin.fields'));
    expect(lynx()->fields->find('price'))->toBeNull();
});

it('cancels a delete', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'price'])->call('confirmDelete')->call('cancelDelete')->assertSet('isConfirmingDelete', false);
    expect(lynx()->fields->find('price'))->not->toBeNull();
});

it('attaches, orders and removes text formatters', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'summary'])
        ->assertViewHas('availableFormatters', fn (array $formatters): bool => array_keys($formatters) === ['smartypants', 'paragraphs', 'markdown', 'external_links', 'heading_anchors'])
        ->assertSee('Curly quotes')
        ->call('addFormatter', 'markdown')
        ->call('addFormatter', 'smartypants')
        ->call('sortFormatter', 'smartypants', 0)
        ->call('save')->assertHasNoErrors();

    expect(lynx()->fields->get('summary')->config()['formatters'])->toBe(['smartypants', 'markdown']);

    Livewire::test(FieldForm::class, ['field' => 'summary'])
        ->assertSet('formatters', ['smartypants', 'markdown'])
        ->call('removeFormatter', 'smartypants')->call('removeFormatter', 'markdown')
        ->call('save')->assertHasNoErrors();

    expect(lynx()->fields->get('summary')->config())->not->toHaveKey('formatters');
});

it('offers no formatters for types they do not apply to', function (): void {
    Livewire::test(FieldForm::class, ['field' => 'price'])->assertViewHas('availableFormatters', [])->assertDontSee('Formatters');
});

it('counts a field used inside a repeater as used by the repeater\'s templates', function (): void {
    lynx()->fields->add('partner', 'page', settings: ['multiple' => false]);
    lynx()->fields->add('credits', 'repeater', settings: ['fields' => [['name' => 'partner']]]);
    lynx()->templates->update('course', ['add' => ['credits']]);

    Livewire::test(FieldList::class)
        ->assertViewHas('usedBy', fn (array $usedBy): bool => $usedBy['partner'] === ['course (in credits)'])
        ->set('template', 'course')->assertSee('partner');
    Livewire::test(FieldForm::class, ['field' => 'partner'])->assertSee('course (in credits)')->assertDontSee('No template uses this field yet.');
});

/*
 * With one language, "translatable" means nothing to the person creating a field, but it can't be
 * changed later. The form leaves it out and makes fields translatable when their type allows,
 * so adding a language later just works.
 */

it('leaves translation out of the field form on a one-language site', function (): void {
    lynx()->languages->delete('pt');

    Livewire::test(FieldForm::class)->set('type', 'text')
        ->assertDontSee('Translatable')
        ->assertDontSee('fallback');
    Livewire::test(FieldForm::class, ['field' => 'title'])
        ->assertDontSee('Translatable')
        ->assertDontSee('Shared by every language')
        ->assertDontSee('When a translation is empty');
});

it('creates translatable fields on a one-language site when the type allows it', function (): void {
    lynx()->languages->delete('pt');

    Livewire::test(FieldForm::class)->set('name', 'subtitle')->set('label.default', 'Subtitle')->set('type', 'text')->call('save')->assertHasNoErrors();
    Livewire::test(FieldForm::class)->set('name', 'seats')->set('label.default', 'Seats')->set('type', 'integer')->call('save')->assertHasNoErrors();

    expect(lynx()->fields->get('subtitle')->isTranslatable())->toBeTrue()
        ->and(lynx()->fields->get('seats')->isTranslatable())->toBeFalse();
});

it('keeps the translatable choice with several languages', function (): void {
    Livewire::test(FieldForm::class)->set('type', 'text')->assertSee('Translatable: one value per language');

    Livewire::test(FieldForm::class)->set('name', 'subtitle')->set('label.default', 'Subtitle')->set('type', 'text')->set('translatable', false)->call('save');

    expect(lynx()->fields->get('subtitle')->isTranslatable())->toBeFalse();
});
