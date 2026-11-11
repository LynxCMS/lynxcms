<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Finder\Admin\FinderScreen;
use LynxCms\Finder\Models\SavedSearch;

/*
 * Acting on what the finder found: changing the ticked pages together (through the pages actions,
 * so permissions apply), saving and opening searches, and exporting every result as CSV.
 */

beforeEach(function (): void {
    courseSite();
    $this->courses = createCourses('alpha', 'beta');
    $this->courses['alpha']->price = 10;
    $this->courses['alpha']->save();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function finderPage(int|string|null $id): Page
{
    return lynx()->pages->get((int) $id) ?? throw new RuntimeException('No page.');
}

it('unpublishes and hides the ticked pages', function (): void {
    $alpha = (int) $this->courses['alpha']->id;

    Livewire::test(FinderScreen::class)
        ->set('selected', [$alpha])
        ->assertSeeHtml('data-finder-bulk')
        ->call('bulk', 'unpublish')
        ->assertSet('selected', []);

    expect(finderPage($alpha)->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue()
        ->and(finderPage($this->courses['beta']->id)->hasStatus(PageStatus::UNPUBLISHED))->toBeFalse();
});

it('trashes the ticked pages once confirmed', function (): void {
    $beta = (int) $this->courses['beta']->id;

    Livewire::test(FinderScreen::class)
        ->set('selected', [$beta])
        ->call('confirmTrash', [$beta])
        ->assertSeeHtml('data-finder-confirm-trash')
        ->call('trash');

    expect(lynx()->pages->findOne("id={$beta}"))->toBeNull();
});

it('refuses changes the user may not make, and pages outside the finder', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'viewer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['viewer']]);
    $this->actingAs(new GenericUser(['id' => 5]));
    $alpha = (int) $this->courses['alpha']->id;

    Livewire::test(FinderScreen::class)->set('selected', [$alpha, lynx()->pages->trashId()])->call('bulk', 'hide');

    expect(finderPage($alpha)->hasStatus(PageStatus::HIDDEN))->toBeFalse();
});

it('saves the search on screen and opens it again', function (): void {
    $screen = Livewire::test(FinderScreen::class)
        ->set('search.groups.0.conditions.0.subject', 'price')
        ->set('search.groups.0.conditions.0.value', '10')
        ->set('saveName', 'Cheap courses')
        ->call('saveSearch');

    $saved = SavedSearch::query()->sole();
    expect($saved->name)->toBe('Cheap courses')
        ->and($saved->owner)->toBe('user:1');

    $screen->call('resetSearch')
        ->assertSet('savedSearch', null)
        ->call('openSearch', $saved->id)
        ->assertSet('search.groups.0.conditions.0.subject', 'price')
        ->assertSet('savedSearch', $saved->id)
        ->assertSeeHtml('<option value="'.$saved->id.'" selected');
});

it('updates the open search, or saves it as a new one', function (): void {
    $screen = Livewire::test(FinderScreen::class)->set('saveName', 'Mine')->call('saveSearch');
    $screen->set('saveName', 'Mine, renamed')->call('saveSearch');
    expect(SavedSearch::query()->pluck('name')->all())->toBe(['Mine, renamed']);

    $screen->set('saveName', 'Copy')->call('saveSearch', true);
    expect(SavedSearch::query()->orderBy('id')->pluck('name')->all())->toBe(['Mine, renamed', 'Copy']);
});

it('asks for a name before saving', function (): void {
    Livewire::test(FinderScreen::class)->set('saveName', '')->call('saveSearch')->assertHasErrors('name');
});

it('deletes the open saved search once confirmed', function (): void {
    Livewire::test(FinderScreen::class)
        ->set('saveName', 'Gone soon')->call('saveSearch')
        ->call('confirmDeleteSearch')
        ->assertSeeHtml('data-finder-confirm-delete')
        ->call('deleteSearch')
        ->assertSet('savedSearch', null);

    expect(SavedSearch::query()->count())->toBe(0);
});

it('does not open another user\'s private search', function (): void {
    $other = SavedSearch::query()->create(['name' => 'Private', 'owner' => 'user:9', 'is_shared' => false, 'query' => ['groups' => []]]);

    Livewire::test(FinderScreen::class)->call('openSearch', $other->id)->assertSet('savedSearch', null);
});

it('exports every result as CSV, keeping titles from running as formulas', function (): void {
    $this->courses['beta']->title = '=HYPERLINK("x")';
    $this->courses['beta']->save();
    $search = ['groups' => [['conditions' => [['subject' => 'template', 'operator' => 'is', 'value' => 'course']]]]];

    $csv = $this->get(route('lynx.admin.finder.export', ['q' => $search]))->assertOk()->streamedContent();

    expect($csv)->toContain('ID,Title,Template')
        ->toContain((string) $this->courses['alpha']->id)
        ->toContain("'=HYPERLINK")
        ->not->toContain(lynx()->pages->get('/courses/')->id.',Courses');
});

it('refuses the export to users who may not view pages', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'nothing', 'grants' => [['permission' => 'admin.access']]]);
    lynx()->run(AssignRoles::class, ['user' => '6', 'roles' => ['nothing']]);
    $this->actingAs(new GenericUser(['id' => 6]));

    $this->get(route('lynx.admin.finder.export'))->assertForbidden();
});
