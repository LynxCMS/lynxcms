<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\EditorTabs;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Pages\Page;
use LynxCms\Tests\Fixtures\ModuleTab;

/*
 * Modules add tabs to the page editor, after the template's tabs and before Settings, for the
 * pages they choose. A tab renders the module's Livewire component with the page's id.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    Livewire::component('fixture.module-tab', ModuleTab::class);
    app(EditorTabs::class)->add('module', 'Module', 'fixture.module-tab', fn (Page $page): bool => $page->template()->name === 'course');
});

function firstCourse(): Page
{
    $course = lynx()->pages->findOne('template=course, include=all');

    if ($course instanceof Page) {
        return $course;
    }

    $course = lynx()->pages->new('course', '/courses/');
    $course->name = 'tabbed';
    $course->title = 'Tabbed';
    $course->save();

    return $course;
}

it('shows a module tab before Settings on the pages it is for', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => firstCourse()->id])
        ->assertSeeInOrder(['Content', 'Module', 'Settings']);
});

it('leaves the tab out on other pages', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => lynx()->pages->get('/courses/')?->id])
        ->assertDontSee('Module');
});

it('renders the module component with the page id when the tab is open', function (): void {
    $id = firstCourse()->id;

    Livewire::test(PageEditor::class, ['pageId' => $id])
        ->set('tab', 'module')
        ->assertSet('tab', 'module')
        ->assertSeeLivewire(ModuleTab::class)
        ->assertSee("Module tab for page {$id}");
});

it('opens Content instead of a module tab the page does not have', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => lynx()->pages->get('/courses/')?->id])
        ->set('tab', 'module')
        ->assertSet('tab', 'content');
});

it('keeps the built-in tabs when a module uses their key', function (): void {
    app(EditorTabs::class)->add('settings', 'Not settings', 'fixture.module-tab', fn (Page $page): bool => true);

    expect(app(EditorTabs::class)->for(firstCourse()))->not->toHaveKey('settings');
});
