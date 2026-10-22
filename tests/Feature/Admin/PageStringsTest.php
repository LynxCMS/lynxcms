<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\PageStrings;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

beforeEach(function (): void {
    courseSite();
    View::addLocation(__DIR__.'/../../Fixtures/views');
    lynx()->templates->add('landing', fields: ['title']);
    $page = lynx()->pages->new('landing', '/');
    $page->name = 'landing';
    $page->title = 'Landing';
    $page->save();
    $this->pageId = (int) lynx()->pages->get('/landing/')?->id;
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('adds a Texts tab when the template declares strings', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => test()->pageId])->assertSee('Texts')
        ->set('tab', 'texts')->assertSeeLivewire(PageStrings::class);

    // A template with no view of its own, so the workbench's views cannot add strings to it.
    lynx()->templates->add('plain', fields: ['title']);
    $plain = lynx()->pages->new('plain', '/');
    $plain->name = 'plain';
    $plain->title = 'Plain';
    $plain->save();
    Livewire::test(PageEditor::class, ['pageId' => (int) lynx()->pages->get('/plain/')?->id])->assertDontSee('Texts');
});

/** The form row of a string key. */
function row(Testable $component, string $key): int
{
    return (int) array_search($key, $component->get('keys'), true);
}

it('edits page texts per language, with the code text as placeholder', function (): void {
    $texts = Livewire::test(PageStrings::class, ['pageId' => test()->pageId])
        ->assertSee('Hero title')->assertSeeHtml('placeholder="Audiovisual productions"')->assertDontSee('Footer note');
    $texts->set('values.'.row($texts, 'hero.title').'.pt', 'Filmes')->call('save')->assertSet('status', 'Saved.');

    expect(DB::table('lynx_strings')->value('value'))->toBe('Filmes');

    $again = Livewire::test(PageStrings::class, ['pageId' => test()->pageId]);
    $again->assertSet('values.'.row($again, 'hero.title').'.pt', 'Filmes')
        ->set('values.'.row($again, 'hero.title').'.pt', '')->call('save');

    expect(DB::table('lynx_strings')->count())->toBe(0);
});

it('edits site texts on their own screen', function (): void {
    $this->get('/lynx/site-texts')->assertOk()->assertSee('Footer note');

    $site = Livewire::test(PageStrings::class, ['pageId' => (int) lynx()->pages->get('/')?->id, 'scope' => 'site']);
    $site->set('values.'.row($site, 'footer.note').'.default', 'Esfera Cúbica, 2026')->call('save')->assertHasNoErrors();

    expect(view('templates.landing', ['page' => lynx()->pages->get('/landing/')])->render())->toContain('Esfera Cúbica, 2026');
});

it('edits the draft\'s texts while the page has a draft', function (): void {
    lynx()->pages->saveDraft(test()->pageId, ['title' => 'Landing (draft)']);
    $texts = Livewire::test(PageStrings::class, ['pageId' => test()->pageId]);
    $texts->set('values.'.row($texts, 'hero.title').'.pt', 'Rascunho')->call('save')
        ->assertSet('status', 'Saved to the draft. The live page has not changed.');

    expect(DB::table('lynx_strings')->value('page_id'))->toBe(lynx()->pages->draft(test()->pageId)?->id);

    $again = Livewire::test(PageStrings::class, ['pageId' => test()->pageId]);
    $again->assertSet('values.'.row($again, 'hero.title').'.pt', 'Rascunho');
});

it('tells the editor the page moved to a new revision', function (): void {
    $texts = Livewire::test(PageStrings::class, ['pageId' => test()->pageId]);
    $texts->set('values.'.row($texts, 'hero.title').'.pt', 'Filmes')->call('save')
        ->assertDispatched('lynx-page-changed', revision: lynx()->pages->get('/landing/')?->revision);
});

it('shows the view each text comes from', function (): void {
    Livewire::test(PageStrings::class, ['pageId' => test()->pageId])->assertSee('templates/landing.blade.php');
    Livewire::test(PageStrings::class, ['pageId' => (int) lynx()->pages->get('/')?->id, 'scope' => 'site'])->assertSee('layouts/strings.blade.php');
});

it('shows string keys and the views declaring them only to superusers', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    Livewire::test(PageStrings::class, ['pageId' => test()->pageId])->assertSeeHtml('>hero.title<')->assertSee('Declared in this view');

    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view'], ['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '7', 'roles' => ['editor']]);
    $this->actingAs(new GenericUser(['id' => 7]));
    Livewire::test(PageStrings::class, ['pageId' => test()->pageId])->assertSee('Hero title')->assertDontSeeHtml('>hero.title<')->assertDontSee('Declared in this view');
});
