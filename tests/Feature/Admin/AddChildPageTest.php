<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;

/*
 * A new child page starts from the Children tab or the page tree, not from the page editor's
 * header, which keeps to the page's status and links.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    createCourses('alpha');
});

it('leaves adding a child page out of the header', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => (int) lynx()->pages->get('/courses/')->id])
        ->assertDontSeeHtml('data-add-child')
        ->set('tab', 'children')
        ->assertSeeHtml('href="'.route('lynx.admin.pages.create', ['parent' => lynx()->pages->get('/courses/')->id]).'"');
});
