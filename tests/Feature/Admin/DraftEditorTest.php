<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Livewire\PageEditor;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    test()->actingAs(new GenericUser(['id' => 1]));
});

function draftEditor(): mixed
{
    return Livewire::test(PageEditor::class, ['pageId' => (int) lynx()->pages->get('/courses/alpha')?->id]);
}

function liveTitle(): string
{
    return (string) lynx()->pages->get('/courses/alpha')?->get('title');
}

it('saves changes as a draft and keeps editing it', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha');
    $alpha->setLanguageName('pt', 'alfa', active: true);
    $alpha->save();

    draftEditor()
        ->assertSeeHtml('wire:click="saveDraft"')
        ->set('values.title.default', 'Alpha draft')
        ->call('saveDraft')
        ->assertDispatched('lynx-notify', message: 'Draft saved. The live page has not changed.')
        ->assertSeeHtml('data-page-draft');

    expect(liveTitle())->toBe('Alpha');

    draftEditor()
        ->assertSet('values.title.default', 'Alpha draft')
        ->assertSeeHtml('wire:click="publishDraft"')
        ->assertSeeHtml('href="/courses/alpha?preview=draft"')
        ->assertSeeHtml('href="/pt/cursos/alfa?preview=draft"')
        ->assertSee('Preview Português')
        ->assertSeeHtml('wire:click="discardDraft"');
});

it('saves to the draft, not the live page, while a draft exists', function (): void {
    lynx()->pages->saveDraft('/courses/alpha', ['price' => 5]);

    draftEditor()->set('values.title.default', 'Second draft')->call('save');

    expect(liveTitle())->toBe('Alpha')
        ->and((string) lynx()->pages->draft((int) lynx()->pages->get('/courses/alpha')?->id)?->get('title'))->toBe('Second draft');
});

it('publishes the draft', function (): void {
    lynx()->pages->saveDraft('/courses/alpha', ['title' => ['default' => 'Alpha live']]);

    draftEditor()->call('publishDraft')->assertDispatched('lynx-notify', message: 'Draft published.')->assertDontSeeHtml('data-page-draft');

    expect(liveTitle())->toBe('Alpha live');
});

it('discards the draft', function (): void {
    lynx()->pages->saveDraft('/courses/alpha', ['title' => ['default' => 'Gone']]);

    draftEditor()->call('discardDraft')->assertSet('values.title.default', 'Alpha');

    expect(lynx()->pages->draft((int) lynx()->pages->get('/courses/alpha')?->id))->toBeNull();
});

it('explains that previews need a signed in user, instead of offering a link that shows the live page', function (): void {
    lynx()->pages->saveDraft('/courses/alpha', ['price' => 5]);
    auth()->forgetUser();
    config(['lynx-admin.middleware' => ['web']]);
    Gate::define(AdminServiceProvider::GATE, fn (?Authenticatable $user = null): bool => true);

    draftEditor()
        ->assertDontSeeHtml('?preview=draft')
        ->assertSeeHtml('data-draft-preview-signin');
});
