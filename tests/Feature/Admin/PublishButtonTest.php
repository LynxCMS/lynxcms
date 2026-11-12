<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\PageStatus;

/*
 * The page editor's Publish button shows when there is something to publish: an unpublished page
 * is saved and goes live, a waiting draft is published. A published page has only Save. Its menu publishes and then leaves the
 * editor: back to the tree, to the page on the site, to a new page beside it, or to the next page.
 * Save, a button of its own (and Cmd/Ctrl+S), saves a live page at once but never publishes an
 * unpublished page or a draft; Save as draft in the menu starts a draft.
 */

beforeEach(function (): void {
    courseSite();
    test()->actingAs(new GenericUser(['id' => 1]));
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
});

function publishEditor(int $pageId): mixed
{
    return Livewire::test(PageEditor::class, ['pageId' => $pageId]);
}

function titleOf(int $pageId): string
{
    return (string) lynx()->pages->get($pageId)?->get('title');
}

it('saves an unpublished page and takes it live in one step', function (): void {
    $this->alpha->setStatus(PageStatus::UNPUBLISHED | PageStatus::HIDDEN)->save();

    publishEditor($this->alpha->id)
        ->assertSeeHtml('data-publish="publish"')
        ->assertSeeHtml('data-save="page"')
        ->set('values.title.default', 'Alpha, launched')
        ->call('publish')
        ->assertDispatched('lynx-notify', type: 'success');

    $page = lynx()->pages->get($this->alpha->id);
    expect($page->hasStatus(PageStatus::UNPUBLISHED))->toBeFalse()
        ->and($page->hasStatus(PageStatus::HIDDEN))->toBeTrue()
        ->and(titleOf($this->alpha->id))->toBe('Alpha, launched');
});

it('takes an unpublished page live even when no field changed', function (): void {
    $this->alpha->setStatus(PageStatus::UNPUBLISHED)->save();

    publishEditor($this->alpha->id)->call('publish');

    expect(lynx()->pages->get($this->alpha->id)->hasStatus(PageStatus::UNPUBLISHED))->toBeFalse();
});

it('keeps an unpublished page unpublished when saved with the shortcut', function (): void {
    $this->alpha->setStatus(PageStatus::UNPUBLISHED)->save();

    publishEditor($this->alpha->id)
        ->assertSeeHtml('data-save="page"')
        ->set('values.title.default', 'Alpha, kept back')
        ->call('save');

    expect(lynx()->pages->get($this->alpha->id)->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue()
        ->and(titleOf($this->alpha->id))->toBe('Alpha, kept back');
});

it('offers only Save on a published page, which goes live at once', function (): void {
    publishEditor($this->alpha->id)
        ->assertSeeHtml('data-publish="save"')
        ->assertDontSeeHtml('data-publish="publish"')
        ->assertSeeHtml('data-publish-then="exit"')
        ->assertSeeHtml('wire:click="saveDraft"')
        ->set('values.title.default', 'Alpha, now')
        ->call('save')
        ->assertSet('hasDraft', false)
        ->assertDispatched('lynx-notify', type: 'success');

    expect(titleOf($this->alpha->id))->toBe('Alpha, now');
});

it('publishes a waiting draft with the edits not saved yet', function (): void {
    publishEditor($this->alpha->id)
        ->set('values.title.default', 'Alpha draft')
        ->call('saveDraft')
        ->assertSeeHtml('data-save="draft"')
        ->set('values.title.default', 'Alpha draft, edited')
        ->call('save');

    expect(titleOf($this->alpha->id))->toBe('Alpha');

    publishEditor($this->alpha->id)
        ->assertSeeHtml('data-publish="publish"')
        ->assertDontSeeHtml('data-publish-draft')
        ->call('publish')
        ->assertSet('hasDraft', false);

    expect(titleOf($this->alpha->id))->toBe('Alpha draft, edited');
});

it('goes back to the page tree after publishing and exiting', function (): void {
    publishEditor($this->alpha->id)
        ->set('values.title.default', 'Alpha, then exit')
        ->call('publish', 'exit')
        ->assertRedirect(route('lynx.admin.pages'));

    expect(titleOf($this->alpha->id))->toBe('Alpha, then exit')
        ->and(session('lynx-notify.type'))->toBe('success');
});

it('opens the page on the site after publishing and viewing', function (): void {
    publishEditor($this->alpha->id)
        ->assertSeeHtml('data-publish-then="view"')
        ->call('publish', 'view')
        ->assertRedirect($this->alpha->url());
});

it('opens a new page beside this one after publishing and adding', function (): void {
    publishEditor($this->alpha->id)
        ->assertSeeHtml('data-publish-then="add"')
        ->call('publish', 'add')
        ->assertRedirect(route('lynx.admin.pages.create', ['parent' => $this->alpha->parentId]));
});

it('opens the next page in the tree\'s order after publishing', function (): void {
    publishEditor($this->alpha->id)
        ->assertSeeHtml('data-publish-then="next"')
        ->call('publish', 'next')
        ->assertRedirect(route('lynx.admin.pages.edit', $this->beta->id));

    publishEditor($this->beta->id)->assertDontSeeHtml('data-publish-then="next"');
});

it('stays in the editor when publishing fails', function (): void {
    publishEditor($this->alpha->id)
        ->set('values.price.default', 'not a number')
        ->call('publish', 'exit')
        ->assertHasErrors()
        ->assertNoRedirect();
});

it('ignores a follow-up it does not know', function (): void {
    publishEditor($this->alpha->id)->call('publish', 'elsewhere')->assertNoRedirect();
});

it('offers saving without the publish permission', function (): void {
    $this->alpha->setStatus(PageStatus::UNPUBLISHED)->save();
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view'], ['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '8', 'roles' => ['writer']]);
    $this->actingAs(new GenericUser(['id' => 8]));

    publishEditor($this->alpha->id)
        ->assertSeeHtml('data-publish="save"')
        ->assertSeeHtml('data-publish-then="exit"')
        ->set('values.title.default', 'Alpha, saved')
        ->call('publish', 'exit')
        ->assertRedirect(route('lynx.admin.pages'));

    expect(lynx()->pages->get($this->alpha->id)->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue()
        ->and(titleOf($this->alpha->id))->toBe('Alpha, saved');
});

it('saves a live page directly for users who may not publish', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view'], ['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '8', 'roles' => ['writer']]);
    $this->actingAs(new GenericUser(['id' => 8]));

    publishEditor($this->alpha->id)
        ->assertDontSeeHtml('data-save="draft"')
        ->set('values.title.default', 'Alpha, fixed')
        ->call('save')
        ->assertSet('hasDraft', false);

    expect(titleOf($this->alpha->id))->toBe('Alpha, fixed');
});
