<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Admin\Livewire\PageSettings;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
});

/**
 * @param  list<array<string, mixed>>  $grants
 */
function signedInWith(array $grants): void
{
    lynx()->run(SaveRole::class, ['name' => 'limited', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view'], ...$grants]]);
    lynx()->run(AssignRoles::class, ['user' => '7', 'roles' => ['limited']]);
    test()->actingAs(new GenericUser(['id' => 7]));
}

it('shows tree actions only where the user may use them', function (): void {
    signedInWith([['permission' => 'pages.edit', 'templates' => ['course']]]);
    $alpha = lynx()->pages->get('/courses/alpha/')->id;
    $courses = lynx()->pages->get('/courses/')->id;

    Livewire::test(PageTree::class)->call('toggle', $courses)
        ->assertSeeHtml('href="'.route('lynx.admin.pages.edit', $alpha).'"')
        ->assertDontSeeHtml('href="'.route('lynx.admin.pages.edit', $courses).'"')
        ->assertDontSeeHtml('wire:click="startMove(')
        ->assertDontSeeHtml('wire:click="confirmDelete(')
        ->assertDontSee('>New<', false);
});

it('shows every action to superusers', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageTree::class)->call('toggle', lynx()->pages->get('/courses/')->id)
        ->assertSeeHtml('wire:click="startMove(')
        ->assertSeeHtml('wire:click="confirmDelete(');
});

it('shows top bar links only for screens the user may open', function (): void {
    signedInWith([['permission' => 'pages.edit']]);

    $this->get('/lynx/pages')->assertOk()
        ->assertDontSee('href="'.route('lynx.admin.templates').'"', false)
        ->assertDontSee('href="'.route('lynx.admin.fields').'"', false)
        ->assertDontSee('href="'.route('lynx.admin.menus').'"', false)
        ->assertDontSee('href="'.route('lynx.admin.roles').'"', false);
    $this->get('/lynx/templates')->assertForbidden();
    $this->get('/lynx/menus')->assertForbidden();
});

it('makes the editor read only without pages.edit on the page', function (): void {
    signedInWith([['permission' => 'pages.edit', 'templates' => ['course']]]);

    Livewire::test(PageEditor::class, ['pageId' => lynx()->pages->get('/courses/')->id])
        ->assertDontSeeHtml('data-lynx-save')
        ->assertSee('You can view this page but not edit it.');
    Livewire::test(PageEditor::class, ['pageId' => lynx()->pages->get('/courses/alpha/')->id])
        ->assertSeeHtml('data-lynx-save');
});

it('shows only the settings the user may change', function (): void {
    signedInWith([['permission' => 'pages.edit'], ['permission' => 'pages.status']]);

    Livewire::test(PageSettings::class, ['pageId' => lynx()->pages->get('/courses/alpha/')->id])
        ->assertSeeHtml('wire:click="toggleStatus(\'hidden\')"')
        ->assertDontSeeHtml('wire:click="toggleStatus(\'unpublished\')"')
        ->assertDontSee('Move here')
        ->assertDontSee('Switch template');
});

it('opens the editor only for pages the user may view', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'courses-only', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view', 'branches' => [lynx()->pages->get('/courses/')->id]]]]);
    lynx()->run(AssignRoles::class, ['user' => '8', 'roles' => ['courses-only']]);
    $this->actingAs(new GenericUser(['id' => 8]));

    $this->get(route('lynx.admin.pages.edit', lynx()->pages->get('/courses/alpha/')->id))->assertOk();
    $this->get(route('lynx.admin.pages.edit', lynx()->pages->get('/')->id))->assertForbidden();
});

it('offers Unlock only to users who may change the status', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha/');
    lynx()->pages->setStatus($alpha, add: ['locked']);
    $unlock = "wire:click=\"toggleStatus({$alpha->id}, 'locked')\"";

    lynx()->run(SaveRole::class, ['name' => 'locksmith', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view'], ['permission' => 'pages.status']]]);
    lynx()->run(AssignRoles::class, ['user' => '9', 'roles' => ['locksmith']]);

    signedInWith([['permission' => 'pages.edit']]);
    Livewire::test(PageTree::class)->call('toggle', lynx()->pages->get('/courses/')->id)->assertDontSeeHtml($unlock);

    // Signing in as someone else starts a new session, without the first user's open branches.
    session()->flush();
    $this->actingAs(new GenericUser(['id' => 9]));
    Livewire::test(PageTree::class)->call('toggle', lynx()->pages->get('/courses/')->id)->assertSeeHtml($unlock);
});

it('shows template and field type names only to superusers', function (): void {
    $alpha = (int) lynx()->pages->get('/courses/alpha/')->id;
    $this->actingAs(new GenericUser(['id' => 1]));
    Livewire::test(PageEditor::class, ['pageId' => $alpha])->assertSee('Template: course')->assertSeeHtml('>text, translatable<');

    signedInWith([['permission' => 'pages.edit']]);
    Livewire::test(PageEditor::class, ['pageId' => $alpha])->assertSee('Alpha')->assertDontSee('Template: course')->assertDontSeeHtml('>text, translatable<');
});
