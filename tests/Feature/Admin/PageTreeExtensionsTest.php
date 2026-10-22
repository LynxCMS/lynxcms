<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use LynxCms\Admin\Admin;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Admin\Navigation;
use LynxCms\Tests\Fixtures\ShoutingPresenter;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    $this->actingAs(new GenericUser(['id' => 1]));
});

it('uses a presenter for titles and details', function (): void {
    Livewire::test(PageTree::class, ['root' => '/courses/', 'presenter' => ShoutingPresenter::class])
        ->assertSee(['ALPHA!', 'detail of alpha'])
        ->assertDontSee('Alpha');
});

it('ignores a presenter class that does not implement the contract', function (): void {
    Livewire::test(PageTree::class, ['root' => '/courses/', 'presenter' => stdClass::class])->assertSee('Alpha');
});

it('dispatches edit and new events instead of links when an event prefix is set', function (): void {
    $alpha = (int) lynx()->pages->get('/courses/alpha/')->id;

    Livewire::test(PageTree::class, ['root' => '/courses/', 'events' => 'thing'])
        ->assertSeeHtml("\$dispatch('thing-edit', { id: {$alpha} })")
        ->assertSeeHtml("\$dispatch('thing-new', { parent: {$alpha} })");
});

it('never moves, deletes or changes the status of the tree root', function (): void {
    $courses = (int) lynx()->pages->get('/courses/')->id;

    Livewire::test(PageTree::class, ['root' => '/courses/'])
        ->assertDontSeeHtml('wire:click="startMove('.$courses.')"')
        ->assertDontSeeHtml('wire:click="confirmDelete('.$courses.')"')
        ->assertDontSeeHtml('wire:click="toggleStatus('.$courses.', \'hidden\')"');
});

it('does not outdent past the tree root', function (): void {
    $courses = (int) lynx()->pages->get('/courses/')->id;
    $alpha = (int) lynx()->pages->get('/courses/alpha/')->id;

    Livewire::test(PageTree::class, ['root' => '/courses/'])
        ->call('outdent', $alpha, $courses, 0)
        ->assertSet('error', 'The page is already at the top level of this tree.');

    expect(lynx()->pages->get($alpha)->parentId)->toBe($courses);
});

it('hides View for pages without a URL', function (): void {
    lynx()->templates->add('snippet', fields: ['title'], hasUrl: false);
    $snippet = lynx()->pages->new('snippet', '/courses/');
    $snippet->name = 'snip';
    $snippet->title = 'Snip';
    $snippet->save();

    $html = Livewire::test(PageTree::class, ['root' => '/courses/'])->html();

    // Courses (the tree root), Alpha and Beta have URLs; Snip does not.
    expect(substr_count($html, '<span>View</span>'))->toBe(3);
});

it('re-renders and opens a branch on the refresh event', function (): void {
    $alpha = (int) lynx()->pages->get('/courses/alpha/')->id;
    lynx()->pages->move('/courses/beta/', $alpha);

    Livewire::test(PageTree::class, ['root' => '/courses/'])
        ->assertDontSee('Beta')
        ->dispatch('lynx-tree-refresh', expand: $alpha)
        ->assertSee('Beta');
});

it('lets modules add navigation items and routes', function (): void {
    app(Navigation::class)->add('Widgets', 'lynx.admin.widgets', order: 50);
    Admin::routes(fn () => Route::get('widgets', fn (): string => 'widgets')->name('widgets'));
    Route::getRoutes()->refreshNameLookups();

    $this->get('/lynx/pages')->assertSeeInOrder(['Pages', 'Widgets']);
    $this->get('/lynx/widgets')->assertOk()->assertSee('widgets');
    $this->getJson('/lynx/widgets', ['Accept' => 'application/json'])->assertOk();
    auth()->forgetUser();
    $this->getJson('/lynx/widgets')->assertUnauthorized();
});

it('refuses writes to pages outside the tree root', function (string $method, array $arguments): void {
    $courses = (int) lynx()->pages->get('/courses/')->id;
    $alpha = (int) lynx()->pages->get('/courses/alpha/')->id;
    $outsider = lynx()->pages->new('courses', '/');
    $outsider->name = 'outside';
    $outsider->title = 'Outside';
    $outsider->save();
    $ids = ['OUT' => (int) $outsider->id, 'ALPHA' => $alpha, 'COURSES' => $courses, 'HOME' => (int) lynx()->pages->get('/')->id];
    $arguments = array_map(fn ($a) => is_string($a) && isset($ids[$a]) ? $ids[$a] : $a, $arguments);

    Livewire::test(PageTree::class, ['root' => '/courses/alpha/'])
        ->call($method, ...$arguments)
        ->assertSet('error', 'This page is not part of this tree.');

    expect(lynx()->pages->get('/outside/'))->not->toBeNull()
        ->and(lynx()->pages->get('/outside/')->status)->toBe(0)
        ->and(lynx()->pages->get($alpha)->parentId)->toBe($courses);
})->with([
    'drop an outsider' => ['handleSort', ['OUT', 0, 'ALPHA']],
    'drop into an outsider' => ['handleSort', ['ALPHA', 0, 'OUT']],
    'move the root up' => ['moveUp', ['ALPHA', 'COURSES', 1]],
    'indent an outsider' => ['indent', ['OUT', 'HOME', 2]],
    'outdent with a forged parent' => ['outdent', ['ALPHA', 'HOME', 0]],
    'hide an outsider' => ['toggleStatus', ['OUT', 'hidden']],
    'delete an outsider' => ['confirmDelete', ['OUT']],
]);
