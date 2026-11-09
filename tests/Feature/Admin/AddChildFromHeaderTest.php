<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;

/*
 * The page editor's header links to the New page screen under the page, on every tab, when the
 * page may have children and the user may create them.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    createCourses('alpha');
});

function addChildLink(string $path): string
{
    return route('lynx.admin.pages.create', ['parent' => lynx()->pages->get($path)->id]);
}

it('links to a new child page from the header', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => (int) lynx()->pages->get('/courses/')->id])
        ->assertSeeHtml('href="'.addChildLink('/courses/').'"')
        ->set('tab', 'settings')
        ->assertSeeHtml('data-add-child');
});

it('offers no child page where the template allows none', function (): void {
    lynx()->templates->update('course', ['allow_children' => false]);

    Livewire::test(PageEditor::class, ['pageId' => (int) lynx()->pages->get('/courses/alpha/')->id])
        ->assertDontSeeHtml('data-add-child');
});
