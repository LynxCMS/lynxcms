<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    lynx()->templates->add('topic', fields: [['name' => 'title', 'required' => true]]);
    $news = lynx()->pages->new('topic', '/');
    $news->name = 'news';
    $news->title = 'Newsroom';
    $news->save();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
});

/**
 * @param  list<array<string, mixed>>  $grants
 */
function treeUser(array $grants): void
{
    lynx()->run(SaveRole::class, ['name' => 'tree-user', 'grants' => [['permission' => 'admin.access'], ...$grants]]);
    lynx()->run(AssignRoles::class, ['user' => '7', 'roles' => ['tree-user']]);
    test()->actingAs(new GenericUser(['id' => 7]));
}

function coursesBranchId(): int
{
    return (int) lynx()->pages->get('/courses/')?->id;
}

it('shows only the pages a user may act on, and the pages above them as the way there', function (): void {
    treeUser([['permission' => 'pages.edit', 'branches' => [coursesBranchId()]]]);

    Livewire::test(PageTree::class)->call('toggle', coursesBranchId())
        ->assertSee('Courses')
        ->assertSee('Alpha')
        ->assertDontSee('Newsroom');
});

it('keeps a template limited user to pages of those templates', function (): void {
    treeUser([['permission' => 'pages.edit', 'templates' => ['topic']]]);

    Livewire::test(PageTree::class)
        ->assertSee('Newsroom')
        ->assertDontSee('Courses');
});

it('counts only the children a user sees', function (): void {
    $notes = lynx()->pages->new('topic', '/courses/');
    $notes->name = 'notes';
    $notes->title = 'Notes';
    $notes->save();
    treeUser([['permission' => 'pages.edit', 'templates' => ['course'], 'branches' => [coursesBranchId()]]]);

    Livewire::test(PageTree::class)->call('toggle', coursesBranchId())
        ->assertSee('Alpha')
        ->assertDontSee('Notes')
        ->assertSeeHtml('data-child-count="2"')
        ->assertDontSeeHtml('data-child-count="3"');
});

it('shows every page to superusers', function (): void {
    test()->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageTree::class)->assertSee('Newsroom')->assertSee('Courses');
});

it('does not reorder among siblings a user cannot see', function (): void {
    treeUser([['permission' => 'pages.edit', 'templates' => ['topic']], ['permission' => 'pages.move', 'templates' => ['topic']]]);
    $news = (int) lynx()->pages->get('/news/')?->id;
    $home = (int) lynx()->pages->get('/')?->id;
    $sort = lynx()->pages->get('/news/')?->sort;

    Livewire::test(PageTree::class)
        ->assertDontSeeHtml('wire:sort:group-id="'.$home.'"')
        ->call('handleSort', $news, 0, $home)
        ->assertSet('error', 'Some pages here are hidden from you, so pages cannot be reordered here.');

    expect(lynx()->pages->get('/news/')?->sort)->toBe($sort);
});

it('shows a module\'s branch to users holding the module\'s permission', function (): void {
    lynx()->menus()->create('top', 'Top');
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'link', 'label' => 'Handbook', 'url' => 'https://example.com']);
    treeUser([['permission' => 'menus.manage'], ['permission' => 'pages.view']]);

    Livewire::test(PageTree::class, ['root' => (string) lynx()->menus()->page('top')?->id])->assertSee('Handbook');
});

it('shows the pages a user may only view, without actions', function (): void {
    treeUser([['permission' => 'pages.view', 'templates' => ['topic']]]);

    Livewire::test(PageTree::class)
        ->assertSee('Newsroom')
        ->assertDontSee('Courses')
        ->assertDontSeeHtml('href="'.route('lynx.admin.pages.edit', (int) lynx()->pages->get('/news/')?->id).'"');
});
