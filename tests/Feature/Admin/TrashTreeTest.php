<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    $lesson = lynx()->pages->new('course', '/courses/alpha');
    $lesson->name = 'lesson';
    $lesson->title = 'Lesson';
    $lesson->save();
    test()->actingAs(new GenericUser(['id' => 1]));
});

function trashTree(): mixed
{
    return Livewire::test(PageTree::class)->call('toggle', (int) lynx()->pages->trashId());
}

it('moves a page to the trash after asking', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha');

    Livewire::test(PageTree::class)->call('toggle', (int) lynx()->pages->get('/courses')?->id)
        ->assertSeeHtml('>Trash</button>')
        ->call('confirmDelete', $alpha->id)
        ->assertSee('to the trash?')
        ->call('delete', $alpha->id);

    expect(lynx()->pages->get($alpha->id)?->parentId)->toBe(lynx()->pages->trashId());
});

it('shows the trash last, with its branches, Restore and Delete for good', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha');
    lynx()->pages->trash($alpha);

    trashTree()
        ->assertSeeInOrder(['Courses', 'Trash', 'Alpha'])
        ->assertSeeHtml('wire:click="emptyTrash"')
        ->assertSeeHtml('wire:click="restore('.$alpha->id.')"')
        ->call('confirmDelete', $alpha->id)
        ->assertSee('for good')
        ->call('delete', $alpha->id);

    expect(lynx()->pages->get($alpha->id))->toBeNull();
});

it('restores a branch from the trash', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha');
    lynx()->pages->trash($alpha);

    trashTree()->call('restore', $alpha->id)->assertSet('error', null);

    expect(lynx()->pages->get($alpha->id)?->url())->toBe('/courses/alpha');
});

it('restores a branch where it is dropped', function (): void {
    $lesson = lynx()->pages->get('/courses/alpha/lesson');
    lynx()->pages->trash($lesson);

    trashTree()->call('handleSort', $lesson->id, 0, (int) lynx()->pages->get('/courses/beta')?->id)->assertSet('error', null);

    expect(lynx()->pages->get($lesson->id)?->url())->toBe('/courses/beta/lesson');
});

it('offers Unlock on locked pages in the trash', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha');
    lynx()->pages->trash($alpha);
    lynx()->pages->setStatus($alpha->id, add: ['locked']);

    trashTree()->assertSeeHtml("wire:click=\"toggleStatus({$alpha->id}, 'locked')\"")
        ->call('toggleStatus', $alpha->id, 'locked');

    expect(lynx()->pages->get($alpha->id)?->hasStatus(PageStatus::LOCKED))->toBeFalse();
});

it('empties the trash and names the locked pages that kept a branch, with links', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha');
    $beta = lynx()->pages->get('/courses/beta');
    $lesson = lynx()->pages->get('/courses/alpha/lesson');
    lynx()->pages->trash($alpha);
    lynx()->pages->trash($beta);
    lynx()->pages->setStatus($lesson->id, add: ['locked']);

    trashTree()->call('emptyTrash')
        ->assertSee('Deleted 1 branch.')
        ->assertSee('Kept because a page is locked:')
        ->assertSeeHtml('href="'.route('lynx.admin.pages.edit', $lesson->id).'"');

    expect(lynx()->pages->get($beta->id))->toBeNull()->and(lynx()->pages->get($alpha->id))->not->toBeNull();
});
