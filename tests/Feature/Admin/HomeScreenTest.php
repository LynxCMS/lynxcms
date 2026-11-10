<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use LynxCms\Admin\Home\HomeOverview;
use LynxCms\Admin\Home\HomeScreen;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;

/*
 * The admin's home screen: pages with a draft, the next scheduled changes, the pages changed
 * last, and panels modules add. Each list holds only pages the user may view.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->courses = createCourses('alpha', 'beta', 'gamma');
});

function homePageLink(int|string|null $id): string
{
    return 'data-home-page="'.$id.'"';
}

it('opens at the admin address, with a link in the sidebar', function (): void {
    $this->get('/lynx')->assertOk()
        ->assertSee('data-home-panel="drafts"', false)
        ->assertSee('data-home-panel="upcoming"', false)
        ->assertSee('data-home-panel="recent"', false)
        ->assertSee('href="'.route('lynx.admin.home').'"', false);
});

it('lists the pages with a draft', function (): void {
    lynx()->pages->saveDraft($this->courses['beta'], ['title' => 'Beta again']);

    $this->get('/lynx')->assertOk()
        ->assertSee(homePageLink($this->courses['beta']->id), false)
        ->assertSee('href="'.route('lynx.admin.pages.edit', $this->courses['beta']->id).'"', false);
    expect(app(HomeOverview::class)->drafts()['total'])->toBe(1);
});

it('shows an empty state for a list with nothing in it', function (): void {
    $this->get('/lynx')->assertOk()->assertSee('data-home-empty', false);
    expect(app(HomeOverview::class)->drafts()['pages'])->toBe([])
        ->and(app(HomeOverview::class)->upcoming()['entries'])->toBe([]);
});

it('lists the scheduled changes soonest first', function (): void {
    lynx()->pages->schedule($this->courses['gamma'], 'default', unpublishAt: now()->addDays(3));
    lynx()->pages->schedule($this->courses['alpha'], 'default', unpublishAt: now()->addDay());

    $upcoming = app(HomeOverview::class)->upcoming();

    expect(array_column($upcoming['entries'], 'id'))->toBe([(int) $this->courses['alpha']->id, (int) $this->courses['gamma']->id])
        ->and(array_column($upcoming['entries'], 'action'))->toBe(['unpublish', 'unpublish'])
        ->and($upcoming['total'])->toBe(2);
});

it('shows one row for the same change at the same time in several languages', function (): void {
    $at = now()->addDay();
    lynx()->pages->schedule($this->courses['alpha'], 'default', unpublishAt: $at);
    lynx()->pages->schedule($this->courses['alpha'], 'pt', unpublishAt: $at);

    $entries = app(HomeOverview::class)->upcoming()['entries'];

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['languages'])->toHaveCount(2);
});

it('lists the pages changed last, once each, newest first', function (): void {
    foreach (['alpha', 'beta', 'alpha'] as $name) {
        $page = lynx()->pages->get($this->courses[$name]->id);
        $page->title = 'Changed';
        lynx()->pages->save($page);
    }

    $ids = array_column(app(HomeOverview::class)->recent(), 'id');

    expect(array_slice($ids, 0, 2))->toBe([(int) $this->courses['alpha']->id, (int) $this->courses['beta']->id])
        ->and($ids)->toBe(array_values(array_unique($ids)));
});

it('leaves out trashed pages', function (): void {
    lynx()->pages->saveDraft($this->courses['beta'], ['title' => 'Beta again']);
    lynx()->pages->trash($this->courses['beta']);

    expect(app(HomeOverview::class)->drafts()['total'])->toBe(0)
        ->and(array_column(app(HomeOverview::class)->recent(), 'id'))->not->toContain((int) $this->courses['beta']->id);
});

it('lists only pages the user may view', function (): void {
    lynx()->pages->saveDraft($this->courses['alpha'], ['title' => 'Alpha again']);
    lynx()->pages->saveDraft(lynx()->pages->get('/'), ['title' => 'Home again']);
    lynx()->run(SaveRole::class, ['name' => 'courses-only', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view', 'branches' => [lynx()->pages->get('/courses/')->id]]]]);
    lynx()->run(AssignRoles::class, ['user' => '8', 'roles' => ['courses-only']]);
    $this->actingAs(new GenericUser(['id' => 8]));

    $this->get('/lynx')->assertOk()
        ->assertSee(homePageLink($this->courses['alpha']->id), false)
        ->assertDontSee(homePageLink(lynx()->pages->get('/')->id), false);
});

it('links to the page tree filter when a list has more than it shows', function (): void {
    $more = createCourses(...array_map(fn (int $n): string => "extra-{$n}", range(0, HomeOverview::LIMIT)));

    foreach ($more as $course) {
        lynx()->pages->saveDraft($course, ['title' => 'Changed']);
    }

    $this->get('/lynx')->assertOk()
        ->assertSee('data-home-see-all', false);
});

it('opens a page tree filter from the link', function (): void {
    lynx()->pages->saveDraft($this->courses['alpha'], ['title' => 'Alpha again']);

    Livewire::withQueryParams(['filter' => 'drafts'])->test(PageTree::class)
        ->assertSet('isDetailed', true)
        ->assertSet('filter', 'drafts')
        ->assertSeeHtml('data-tree-filtered');
});

it('shows the panels modules add, to the users their closure accepts', function (): void {
    $views = testingPath('home-views');
    File::ensureDirectoryExists($views);
    File::put($views.'/shown.blade.php', '<section data-home-panel="module-shown"></section>');
    File::put($views.'/hidden.blade.php', '<section data-home-panel="module-hidden"></section>');
    View::addNamespace('home-test', $views);
    app(HomeScreen::class)->panel('home-test::shown');
    app(HomeScreen::class)->panel('home-test::hidden', fn (): bool => false);

    $this->get('/lynx')->assertOk()
        ->assertSee('data-home-panel="module-shown"', false)
        ->assertDontSee('data-home-panel="module-hidden"', false);
});

it('orders module panels by their order, then as added', function (): void {
    $home = new HomeScreen;
    $home->panel('late', order: 90);
    $home->panel('first');
    $home->panel('second');
    $home->panel('early', order: 10);

    expect($home->panels())->toBe(['early', 'first', 'second', 'late']);
});

it('welcomes the user by name', function (): void {
    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Ana Lima']));

    $this->get('/lynx')->assertOk()->assertSee('data-home-welcome', false)->assertSee('Ana Lima');
});

it('shows the stats, each linking where it has somewhere to go', function (): void {
    $before = collect(app(HomeScreen::class)->stats())->keyBy('key')['pages']['value'];
    createCourses('delta');
    $stats = collect(app(HomeScreen::class)->stats())->keyBy('key');

    expect($stats['pages']['value'])->toBe($before + 1)
        ->and($stats['pages']['url'])->toBe(route('lynx.admin.pages'))
        ->and($stats['unpublished']['url'])->toBeNull();
    $this->get('/lynx')->assertOk()->assertSee('data-home-stat="pages"', false);
});

it('counts the pages not published', function (): void {
    lynx()->pages->setStatus($this->courses['alpha'], add: ['unpublished']);

    expect(collect(app(HomeScreen::class)->stats())->keyBy('key')['unpublished']['value'])->toBe(1);
});

it('leaves out a stat whose value is null, and those the user may not see', function (): void {
    $home = new HomeScreen;
    $home->stat('shown', 'Shown', fn (): int => 3);
    $home->stat('empty', 'Empty', fn (): ?int => null);
    $home->stat('hidden', 'Hidden', fn (): int => 1, visible: fn (): bool => false);

    expect(array_column($home->stats(), 'key'))->toBe(['shown']);
});

it('offers a new page under the home page to those who may create one', function (): void {
    $create = route('lynx.admin.pages.create', ['parent' => lynx()->pages->get('/')->id]);
    $this->get('/lynx')->assertOk()->assertSee('data-home-action="new-page"', false)->assertSee('href="'.e($create).'"', false);

    lynx()->run(SaveRole::class, ['name' => 'viewer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '9', 'roles' => ['viewer']]);
    $this->actingAs(new GenericUser(['id' => 9]));

    $this->get('/lynx')->assertOk()->assertDontSee('data-home-action="new-page"', false)->assertDontSee('data-home-action="site-texts"', false);
});

it('links each list\'s heading to the full list', function (): void {
    $this->get('/lynx')->assertOk()
        ->assertSee('href="'.route('lynx.admin.pages', ['filter' => 'drafts']).'"', false)
        ->assertSee('href="'.route('lynx.admin.pages', ['filter' => 'scheduled']).'"', false);
});
