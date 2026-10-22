<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PagePicker;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Permissions\Role;
use LynxCms\Menus\Admin\MenuEditor;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    lynx()->templates->add('topic', fields: [['name' => 'title', 'required' => true]]);
    $news = lynx()->pages->new('topic', '/');
    $news->name = 'news';
    $news->title = 'Newsroom updates';
    $news->save();
    lynx()->menus()->create('top', 'Top');
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
});

/** A menus manager who may view the courses branch only. */
function menuManager(): void
{
    lynx()->run(SaveRole::class, ['name' => 'menu-manager', 'grants' => [
        ['permission' => 'admin.access'],
        ['permission' => 'menus.manage'],
        ['permission' => 'pages.view', 'branches' => [(int) lynx()->pages->get('/courses/')?->id]],
    ]]);
    lynx()->run(AssignRoles::class, ['user' => '8', 'roles' => ['menu-manager']]);
    test()->actingAs(new GenericUser(['id' => 8]));
}

it('refuses a role granting menus.manage without pages.view', function (): void {
    expect(fn (): mixed => lynx()->run(SaveRole::class, ['name' => 'menus-only', 'grants' => [['permission' => 'menus.manage']]]))
        ->toThrow(ValidationException::class, '[menus.manage] needs [pages.view] in the same role.');
});

it('accepts menus.manage with pages.view limited to a branch', function (): void {
    expect(lynx()->run(SaveRole::class, ['name' => 'menu-manager-copy', 'grants' => [
        ['permission' => 'menus.manage'],
        ['permission' => 'pages.view', 'templates' => ['course']],
    ]])->value)->toBeInstanceOf(Role::class);
});

it('offers in the menu page search only the pages the user may view', function (): void {
    menuManager();

    Livewire::test(MenuEditor::class, ['menuId' => (int) lynx()->menus()->page('top')?->id])
        ->call('newItem', (int) lynx()->menus()->page('top')?->id)
        ->set('item.kind', 'page')
        ->set('pageSearch', 'es')
        ->assertSee('Courses')
        ->assertDontSee('Newsroom');
});

it('refuses linking a page the user may not view, in MCP and the agent too', function (): void {
    menuManager();

    expect(fn (): mixed => lynx()->actingAs(Actor::mcp(8), fn () => lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/news/'])))
        ->toThrow(AuthorizationException::class, '[pages.view]')
        ->and(lynx()->actingAs(Actor::mcp(8), fn () => lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => '/courses/alpha/']))->id)->toBeInt();
});

it('offers in the page picker only the pages the user may view', function (): void {
    menuManager();

    Livewire::test(PagePicker::class, ['value' => []])
        ->set('search', 'es')
        ->assertSee('Courses')
        ->assertDontSee('Newsroom');
});

it('keeps showing pages already chosen in a field, even ones the user may not view', function (): void {
    $news = (int) lynx()->pages->get('/news/')?->id;
    menuManager();

    Livewire::test(PagePicker::class, ['value' => [$news]])
        ->assertSee('Newsroom updates')
        ->assertSeeHtml('wire:click="remove('.$news.')"');
});
