<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Pages\PageStatus;

/*
 * The page editor's header: the title, then a line saying whether the page is live, whether a
 * draft waits, and a link to the live page, so the buttons beside it can be only actions.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->alpha = createCourses('alpha')['alpha'];
});

it('says beside the title whether the page is published', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alpha->id])
        ->assertSeeHtml('data-page-status="published"')
        ->assertSeeHtml('href="'.$this->alpha->url().'"');

    $this->alpha->setStatus(PageStatus::UNPUBLISHED)->save();

    Livewire::test(PageEditor::class, ['pageId' => $this->alpha->id])
        ->assertSeeHtml('data-page-status="unpublished"');
});

it('marks a waiting draft beside the title', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => $this->alpha->id])
        ->assertDontSeeHtml('data-page-draft')
        ->set('values.title.default', 'Alpha draft')
        ->call('saveDraft')
        ->assertSeeHtml('data-page-draft');
});
