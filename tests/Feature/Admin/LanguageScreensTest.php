<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\LanguageForm;
use LynxCms\Admin\Livewire\LanguageList;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('lists languages with their prefix, fallback and tab label', function (): void {
    lynx()->languages->update('pt', ['label' => 'PT']);

    $this->get('/lynx/languages')->assertOk()->assertSeeLivewire(LanguageList::class)->assertSee('New language');
    Livewire::test(LanguageList::class)
        ->assertSeeHtml('languages/pt/edit')
        ->assertSee('Português')
        ->assertSee('PT')
        ->assertSee('default');
});

it('creates a language', function (): void {
    Livewire::test(LanguageForm::class)
        ->set('code', 'es')->set('name', 'Español')->set('label', 'ES')->set('fallback', 'pt')
        ->call('save')
        ->assertRedirect(route('lynx.admin.languages.edit', 'es'));

    $es = lynx()->languages->get('es');
    expect($es->tabLabel())->toBe('ES')->and($es->url_prefix)->toBe('es')->and(lynx()->languages->get((int) $es->fallback_id)->code)->toBe('pt');
});

it('shows validation errors by their inputs', function (): void {
    Livewire::test(LanguageForm::class)->set('code', 'PT!')->set('name', '')->call('save')->assertHasErrors(['code', 'name']);
});

it('updates a language and clears its label', function (): void {
    lynx()->languages->update('pt', ['label' => 'PT']);

    Livewire::test(LanguageForm::class, ['language' => 'pt'])
        ->assertSet('label', 'PT')
        ->set('name', 'Portuguese')->set('label', '')->set('active', false)
        ->call('save')->assertSet('status', 'Saved.');

    $pt = lynx()->languages->get('pt');
    expect($pt->name)->toBe('Portuguese')->and($pt->label)->toBeNull()->and($pt->is_active)->toBeFalse();
});

it('deletes a language after showing what goes with it', function (): void {
    Livewire::test(LanguageForm::class, ['language' => 'pt'])
        ->call('confirmDelete')
        ->assertSet('isConfirmingDelete', true)
        ->assertSee('Delete language pt')
        ->call('delete')
        ->assertRedirect(route('lynx.admin.languages'));

    expect(lynx()->languages->find('pt'))->toBeNull();
});

it('never offers to delete the default language', function (): void {
    Livewire::test(LanguageForm::class, ['language' => 'default'])->assertDontSee('Delete language')->call('confirmDelete')->assertForbidden();
});

it('is read only when schema editing is off', function (): void {
    config(['lynx-admin.schema_editing' => false]);

    $this->get('/lynx/languages')->assertOk()->assertDontSee('New language');
    $this->get('/lynx/languages/create')->assertForbidden();
    Livewire::test(LanguageForm::class, ['language' => 'pt'])->set('name', 'X')->call('save')->assertForbidden();
});

it('needs schema.manage', function (): void {
    DB::table('users')->insert(['id' => 2, 'name' => 'Bruno', 'email' => 'bruno@example.com', 'password' => 'x']);
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['writer']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    $this->get('/lynx/languages')->assertForbidden();
    $this->get('/lynx/languages/pt/edit')->assertForbidden();
});

it('returns 404 for an unknown language', function (): void {
    $this->get('/lynx/languages/xx/edit')->assertNotFound();
});

it('labels the editor tabs with the tab label', function (): void {
    lynx()->languages->update('pt', ['label' => 'PT']);

    Livewire::test(PageEditor::class, ['pageId' => (int) lynx()->pages->get('/courses/')->id])
        ->assertViewHas('languages', fn (array $languages): bool => $languages[1]['label'] === 'PT' && $languages[0]['label'] === 'Default');
});
