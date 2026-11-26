<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageCreator;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

/*
 * The New page screen shows the parent's title and address, so it opens only for a user who may
 * create a page under that parent, and offers only the templates they may create there.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    lynx()->templates->add('topic', fields: [['name' => 'title', 'required' => true], 'price']);
    $news = lynx()->pages->new('topic', '/');
    $news->name = 'news';
    $news->title = 'Newsroom';
    $news->save();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
});

/**
 * @param  list<array<string, mixed>>  $grants
 */
function creatorUser(array $grants): void
{
    lynx()->run(SaveRole::class, ['name' => 'creator', 'grants' => [['permission' => 'admin.access'], ...$grants]]);
    lynx()->run(AssignRoles::class, ['user' => '7', 'roles' => ['creator']]);
    test()->actingAs(new GenericUser(['id' => 7]));
}

function newsId(): int
{
    return (int) lynx()->pages->get('/news/')?->id;
}

function coursesPageId(): int
{
    return (int) lynx()->pages->get('/courses/')?->id;
}

it('refuses the screen for a parent the user may not create under', function (): void {
    creatorUser([['permission' => 'pages.create', 'branches' => [newsId()]]]);

    $this->get(route('lynx.admin.pages.create', ['parent' => coursesPageId()]))->assertForbidden();
    $this->get(route('lynx.admin.pages.create', ['parent' => newsId()]))->assertOk();
});

it('refuses the component for a parent the user may not create under', function (): void {
    creatorUser([['permission' => 'pages.create', 'branches' => [newsId()]]]);

    Livewire::test(PageCreator::class, ['parentId' => coursesPageId()])->assertForbidden();
});

it('offers only the templates the user may create under the parent', function (): void {
    creatorUser([['permission' => 'pages.create', 'templates' => ['course']]]);

    $creator = Livewire::test(PageCreator::class, ['parentId' => coursesPageId()])
        ->assertSet('template', 'course');

    expect(array_keys($creator->viewData('choices')))->toBe(['course']);
});

it('renders no fields of a template the browser sets outside the choices', function (): void {
    creatorUser([['permission' => 'pages.create', 'templates' => ['course']]]);

    Livewire::test(PageCreator::class, ['parentId' => coursesPageId()])
        ->set('template', 'topic')
        ->assertSet('template', 'course');
});
