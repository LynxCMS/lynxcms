<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

/*
 * The page editor turns the ids of a page reference field into titles in its inputs and in
 * "Review changes". The browser can put any id there, so only pages the user may view get a title.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    lynx()->templates->add('topic', fields: [['name' => 'title', 'required' => true]]);
    $news = lynx()->pages->new('topic', '/');
    $news->name = 'news';
    $news->title = 'Newsroom';
    $news->save();
    lynx()->pages->setStatus($news, add: ['unpublished']);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    $courses = (int) lynx()->pages->get('/courses/')->id;
    lynx()->run(SaveRole::class, ['name' => 'courses-editor', 'grants' => [
        ['permission' => 'admin.access'],
        ['permission' => 'pages.view', 'branches' => [$courses]],
        ['permission' => 'pages.edit', 'branches' => [$courses]],
    ]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['courses-editor']]);
    $this->actingAs(new GenericUser(['id' => 5]));
});

function hiddenNewsId(): int
{
    return (int) lynx()->pages->get('/news/')?->id;
}

function referencingPageId(): int
{
    return (int) lynx()->pages->get('/courses/alpha/')->id;
}

it('shows no title in Review changes for a page the user may not view', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => referencingPageId()])
        ->set('values.related.default', [hiddenNewsId()])
        ->call('review')
        ->assertDontSee('Newsroom');
});

it('shows no title in a select for a page the user may not view', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    lynx()->fields->update('related', ['settings' => ['input' => 'select', 'multiple' => false]]);
    $this->actingAs(new GenericUser(['id' => 5]));

    Livewire::test(PageEditor::class, ['pageId' => referencingPageId()])
        ->set('values.related.default', (string) hiddenNewsId())
        ->assertDontSee('Newsroom');
});

it('still shows titles of pages the user may view', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => referencingPageId()])
        ->set('values.related.default', [(int) lynx()->pages->get('/courses/beta/')->id])
        ->call('review')
        ->assertSee('Beta');
});
