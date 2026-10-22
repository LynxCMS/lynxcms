<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function settingsPage(): Page
{
    $page = lynx()->pages->new('course', '/courses');
    $page->name = 'delivery-8';
    $page->title = 'Delivery';
    $page->price = 249;
    $page->setLanguageName('pt', 'guia-entrega');
    $page->save();

    return lynx()->pages->get($page->id);
}

function settingsFor(Page $page): Testable
{
    return Livewire::test(PageSettings::class, ['pageId' => $page->id]);
}

it('adds a Settings tab to the editor', function (): void {
    $page = settingsPage();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSee(['Content', 'Settings'])
        ->set('tab', 'settings')
        ->assertSeeLivewire(PageSettings::class);
});

it('shows names per language with their URLs', function (): void {
    $page = settingsPage();

    settingsFor($page)
        ->assertSet('names.default', 'delivery-8')
        ->assertSet('names.pt', 'guia-entrega')
        ->assertSee(['/courses/delivery-8', '/pt/cursos/guia-entrega']);
});

it('renames a page and keeps the old URL as a redirect', function (): void {
    $page = settingsPage();

    settingsFor($page)
        ->set('names.default', 'Delivery Guide 8')
        ->assertSee('/courses/delivery-guide-8')
        ->call('saveNames')
        ->assertHasNoErrors()
        ->assertDispatched('lynx-page-changed');

    expect(lynx()->pages->get($page->id)->path('default'))->toBe('courses/delivery-guide-8');
    // assertRedirect() trims trailing slashes, so check the Location header itself.
    $this->get('/courses/delivery-8')->assertStatus(301)->assertHeader('Location', 'http://localhost/courses/delivery-guide-8');
});

it('turns a language off for the page', function (): void {
    $page = settingsPage();

    settingsFor($page)
        ->set('active.pt', false)
        ->call('saveNames')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->isActiveIn('pt'))->toBeFalse();
});

it('shows core errors for a name already taken', function (): void {
    $page = settingsPage();
    createCourses('taken');

    settingsFor($page)->set('names.default', 'taken')->call('saveNames')->assertHasErrors('names.default');
});

it('refuses to rename over a newer save', function (): void {
    $page = settingsPage();
    $settings = settingsFor($page);
    lynx()->run(SavePage::class, ['id' => $page->id, 'values' => ['price' => 1]]);

    $settings->set('names.default', 'other')->call('saveNames')->assertHasErrors('conflict');
});

it('changes status flags', function (): void {
    $page = settingsPage();

    settingsFor($page)
        ->call('toggleStatus', 'hidden')
        ->call('toggleStatus', 'unpublished')
        ->assertHasNoErrors()
        ->assertDispatched('lynx-page-changed');

    $saved = lynx()->pages->get($page->id);
    expect($saved->hasStatus(PageStatus::HIDDEN))->toBeTrue()
        ->and($saved->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue();
});

it('shows each status as a checkbox with what it means', function (): void {
    $page = settingsPage();
    lynx()->pages->setStatus($page->id, add: ['hidden']);

    settingsFor($page)
        ->assertSeeHtml('wire:click="toggleStatus(\'hidden\')" checked')
        ->assertSeeHtml('wire:click="toggleStatus(\'unpublished\')"')
        ->assertSee('Unpublished:')
        ->assertSee('Excluded from lists, menus and searches')
        ->assertDontSee('aria-pressed', escape: false);
});

it('marks a page unique', function (): void {
    $page = settingsPage();

    settingsFor($page)
        ->assertSee('Require the name “delivery-8” to be unique across the site (default language only)')
        ->call('toggleStatus', 'unique')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->hasStatus(PageStatus::UNIQUE))->toBeTrue();
});

it('explains why a page cannot be unique', function (): void {
    $page = settingsPage();
    $other = lynx()->pages->new('course', '/');
    $other->name = 'delivery-8';
    $other->title = 'Elsewhere';
    $other->save();

    settingsFor($page)->call('toggleStatus', 'unique')->assertHasErrors('add')->assertSee('already used by /delivery-8/');
});

it('offers no unique status for the home page', function (): void {
    settingsFor(lynx()->pages->get('/'))->assertDontSee('Unique:');
});

it('warns about orphaned fields before switching the template', function (): void {
    lynx()->templates->add('brief', fields: [['name' => 'title', 'required' => true]]);
    $page = settingsPage();

    $settings = settingsFor($page)
        ->set('template', 'brief')
        ->call('previewTemplate')
        ->assertSet('orphans', ['summary', 'body', 'price', 'related'])
        ->assertSee('kept, but no longer shown');

    expect(lynx()->pages->get($page->id)->template()->name)->toBe('course');

    $settings->call('switchTemplate')->assertHasNoErrors()->assertDispatched('lynx-page-changed', fieldsChanged: true);
    expect(lynx()->pages->get($page->id)->template()->name)->toBe('brief');
});

it('moves the page under another parent', function (): void {
    $page = settingsPage();
    $archive = lynx()->pages->new('courses', '/');
    $archive->name = 'archive';
    $archive->title = 'Archive';
    $archive->save();

    settingsFor($page)
        ->set('parent', [(int) $archive->id])
        ->call('move')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->parentId)->toBe((int) $archive->id);
});

it('shows core errors for an impossible move', function (): void {
    $page = settingsPage();
    $child = lynx()->pages->new('course', $page);
    $child->name = 'child';
    $child->title = 'Child';
    $child->save();

    settingsFor($page)->set('parent', [(int) $child->id])->call('move')->assertHasErrors('parent');
});

it('offers no names, move or template switch for the home page', function (): void {
    settingsFor(lynx()->pages->get('/'))
        ->assertDontSeeHtml('wire:click="saveNames"')
        ->assertDontSeeHtml('wire:click="move"')
        ->assertSee('The home page has no URL name');
});

it('keeps the editor saving without a conflict after its own settings changes', function (): void {
    $page = settingsPage();
    $editor = Livewire::test(PageEditor::class, ['pageId' => $page->id]);
    settingsFor($page)->set('names.default', 'renamed')->call('saveNames');
    $revision = (int) DB::table('lynx_pages')->where('id', $page->id)->value('revision');

    $editor->dispatch('lynx-page-changed', revision: $revision, fieldsChanged: false)
        ->set('values.price.default', '300')
        ->call('save')
        ->assertHasNoErrors();

    expect(lynx()->pages->get($page->id)->price)->toBe(300);
});

it('shows the content Save button only on the Content tab', function (): void {
    $page = settingsPage();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSeeHtml('wire:click="save"')
        ->set('tab', 'settings')
        ->assertDontSeeHtml('wire:click="save"');
});
