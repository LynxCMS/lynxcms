<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageChildren;

/*
 * Moving the selected children of the Children tab under another parent: chosen in a page picker,
 * then confirmed. Each page moves with pages.move; one it refuses stays and the others go on.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    createCourses('alpha', 'beta', 'gamma');

    $archive = lynx()->pages->new('courses', '/');
    $archive->name = 'archive';
    $archive->title = 'Archive';
    $archive->save();
});

/** @return list<int> */
function childIdsOf(string ...$names): array
{
    return array_map(fn (string $name): int => (int) lynx()->pages->get("/courses/{$name}/")->id, $names);
}

function moveChildrenTab(): Testable
{
    return Livewire::test(PageChildren::class, ['pageId' => (int) lynx()->pages->get('/courses/')->id]);
}

it('moves the selected children under the chosen parent', function (): void {
    $ids = childIdsOf('alpha', 'gamma');

    moveChildrenTab()->set('selected', $ids)->call('chooseParent')->assertSet('moving', $ids)
        ->set('moveTo', [(int) lynx()->pages->get('/archive/')->id])->call('move')
        ->assertSet('moving', [])->assertSet('selected', []);

    expect(childNamesOf())->toBe(['beta'])
        ->and(childNamesOf('/archive/'))->toBe(['alpha', 'gamma']);
});

it('asks for a parent before moving', function (): void {
    moveChildrenTab()->set('selected', childIdsOf('alpha'))->call('chooseParent')->call('move')->assertHasErrors('moveTo');

    expect(childNamesOf())->toBe(['alpha', 'beta', 'gamma']);
});

it('moves the others when the new parent refuses one', function (): void {
    $clash = lynx()->pages->new('course', '/archive/');
    $clash->name = 'beta';
    $clash->title = 'Beta';
    $clash->save();

    moveChildrenTab()->set('selected', childIdsOf('alpha', 'beta'))->call('chooseParent')
        ->set('moveTo', [(int) lynx()->pages->get('/archive/')->id])->call('move');

    expect(childNamesOf())->toBe(['beta', 'gamma'])
        ->and(childNamesOf('/archive/'))->toBe(['beta', 'alpha']);
});

it('forgets the move when cancelled', function (): void {
    moveChildrenTab()->set('selected', childIdsOf('alpha'))->call('chooseParent')->call('cancelMove')
        ->assertSet('moving', [])->assertSet('moveTo', []);
});

it('offers only parents whose templates take the selected pages', function (): void {
    lynx()->templates->update('course', ['parent_templates' => ['courses']]);

    $tab = moveChildrenTab()->set('selected', childIdsOf('alpha'))->call('chooseParent');

    expect($tab->viewData('moveParents'))->toBe(['courses']);
});

it('says so when no template can take the selected pages', function (): void {
    lynx()->templates->update('course', ['parent_templates' => ['courses']]);
    lynx()->templates->update('courses', ['child_templates' => ['courses']]);

    $tab = moveChildrenTab()->set('selected', childIdsOf('alpha'))->call('chooseParent');

    expect($tab->viewData('moveParents'))->toBe([])
        ->and($tab->html())->not->toContain('lynx-admin.page-picker');
});

it('offers no parent when only the current one could take the pages', function (): void {
    lynx()->templates->update('course', ['parent_templates' => ['courses']]);
    lynx()->pages->trash('/archive/');

    expect(moveChildrenTab()->set('selected', childIdsOf('alpha'))->call('chooseParent')->viewData('moveParents'))->toBe([]);
});
