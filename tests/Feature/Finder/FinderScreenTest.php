<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Finder\Admin\FinderScreen;
use LynxCms\Finder\FinderResults;

/*
 * The Find pages screen (FinderScreen): conditions in groups, the pages they find (only those the
 * user may view, outside the trash and module branches) and the selector they make.
 */

beforeEach(function (): void {
    courseSite();
    $this->courses = createCourses('alpha', 'beta');
    $this->courses['alpha']->price = 10;
    $this->courses['alpha']->save();
    $this->courses['beta']->price = 60;
    $this->courses['beta']->save();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function finderRow(int|string|null $id): string
{
    return 'data-finder-row="'.$id.'"';
}

it('opens from the sidebar with its shortcut', function (): void {
    $this->get('/lynx')->assertOk()->assertSee('data-shortcut="g q"', false);
    $this->get('/lynx/finder')->assertOk()->assertSee('data-finder', false);
});

it('refuses users who may not view pages', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'nothing', 'grants' => [['permission' => 'admin.access']]]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['nothing']]);
    $this->actingAs(new GenericUser(['id' => 5]));

    $this->get('/lynx/finder')->assertForbidden();
});

it('keeps checking the admin gate on Livewire updates, which skip the admin routes', function (): void {
    $screen = Livewire::test(FinderScreen::class);
    Gate::define(AdminServiceProvider::GATE, fn (): bool => false);

    $screen->call('next')->assertForbidden();
});

it('lists every page before any condition is set', function (): void {
    Livewire::test(FinderScreen::class)
        ->assertSeeHtml(finderRow($this->courses['alpha']->id))
        ->assertSeeHtml(finderRow($this->courses['beta']->id))
        ->assertSeeHtml('sort=-modified');
});

it('gives a new subject its first operator and an empty value', function (): void {
    Livewire::test(FinderScreen::class)
        ->set('search.groups.0.conditions.0.value', 'left over')
        ->set('search.groups.0.conditions.0.subject', 'price')
        ->assertSet('search.groups.0.conditions.0.operator', 'is')
        ->assertSet('search.groups.0.conditions.0.value', '');
});

it('finds the pages matching a condition and shows the selector', function (): void {
    Livewire::test(FinderScreen::class)
        ->set('search.groups.0.conditions.0.subject', 'price')
        ->set('search.groups.0.conditions.0.operator', 'gte')
        ->set('search.groups.0.conditions.0.value', '50')
        ->assertSeeHtml(finderRow($this->courses['beta']->id))
        ->assertDontSeeHtml(finderRow($this->courses['alpha']->id))
        ->assertSeeHtml('price&gt;=50, sort=-modified');
});

it('adds and removes groups and conditions', function (): void {
    Livewire::test(FinderScreen::class)
        ->call('addGroup')
        ->call('addCondition', 1)
        ->assertCount('search.groups', 2)
        ->assertCount('search.groups.1.conditions', 2)
        ->call('removeCondition', 1, 0)
        ->assertCount('search.groups.1.conditions', 1)
        ->call('removeGroup', 0)
        ->assertCount('search.groups', 1)
        ->call('removeGroup', 0)
        ->assertCount('search.groups', 1);
});

it('opens a search from its link', function (): void {
    $search = ['match' => 'all', 'groups' => [['match' => 'any', 'conditions' => [
        ['subject' => 'price', 'operator' => 'lt', 'value' => '20'],
        ['subject' => 'title', 'operator' => 'is', 'value' => 'Beta'],
    ]]], 'sort' => 'title'];

    Livewire::withQueryParams(['q' => $search])->test(FinderScreen::class)
        ->assertSeeHtml(finderRow($this->courses['alpha']->id))
        ->assertSeeHtml(finderRow($this->courses['beta']->id))
        ->assertSeeHtml('(price&lt;20), (title=&quot;Beta&quot;), sort=title');
});

it('lists only pages the user may view', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'courses-only', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view', 'branches' => [lynx()->pages->get('/courses/')->id]]]]);
    lynx()->run(AssignRoles::class, ['user' => '8', 'roles' => ['courses-only']]);
    $this->actingAs(new GenericUser(['id' => 8]));

    Livewire::test(FinderScreen::class)
        ->assertSeeHtml(finderRow($this->courses['alpha']->id))
        ->assertDontSeeHtml(finderRow(lynx()->pages->get('/')->id));
});

it('leaves out pages in the trash', function (): void {
    lynx()->pages->trash($this->courses['beta']);

    Livewire::test(FinderScreen::class)->assertDontSeeHtml(finderRow($this->courses['beta']->id));
});

it('pages through many results', function (): void {
    createCourses(...array_map(fn (int $n): string => "extra-{$n}", range(1, FinderResults::PER_PAGE)));

    Livewire::test(FinderScreen::class)
        ->assertSeeHtml('data-finder-paging')
        ->call('next')
        ->assertSet('start', FinderResults::PER_PAGE)
        ->call('previous')
        ->assertSet('start', 0);
});
