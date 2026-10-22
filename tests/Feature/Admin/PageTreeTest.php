<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageTree;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function pageId(string $path): int
{
    return (int) lynx()->pages->get($path)->id;
}

it('renders the root and its children with child counts', function (): void {
    createCourses('alpha', 'beta');

    Livewire::test(PageTree::class)
        ->assertSeeInOrder(['Home', 'Courses', '2'])
        ->assertDontSee('Alpha');
});

it('reopens the branches left open when coming back without them in the URL', function (): void {
    createCourses('alpha');
    $courses = pageId('/courses/');

    Livewire::test(PageTree::class)->call('toggle', $courses)->assertSee('Alpha');

    Livewire::test(PageTree::class)->assertSee('Alpha');
    Livewire::test(PageTree::class, ['root' => '/courses/'])->assertSet('expanded', [$courses]);
});

it('lets the URL win over the remembered branches', function (): void {
    createCourses('alpha');
    Livewire::test(PageTree::class)->call('toggle', pageId('/courses/'));

    Livewire::withQueryParams(['open' => [pageId('/')]])->test(PageTree::class)->assertDontSee('Alpha');
});

it('forgets a branch once it is closed', function (): void {
    createCourses('alpha');
    $courses = pageId('/courses/');
    Livewire::test(PageTree::class)->call('toggle', $courses)->call('toggle', $courses);

    Livewire::test(PageTree::class)->assertDontSee('Alpha');
});

it('expands and collapses a page', function (): void {
    createCourses('alpha');
    $courses = pageId('/courses/');

    Livewire::test(PageTree::class)
        ->call('toggle', $courses)
        ->assertSee('Alpha')
        ->call('toggle', $courses)
        ->assertDontSee('Alpha');
});

it('pages through children with a numbered pager', function (): void {
    createCourses('alpha', 'beta', 'gamma');
    $courses = pageId('/courses/');

    Livewire::test(PageTree::class, ['perPage' => 2])
        ->call('toggle', $courses)
        ->assertSee(['Alpha', 'Beta'])
        ->assertDontSee('Gamma')
        ->assertSeeHtml('wire:click="goToPage('.$courses.', 2)"')
        ->call('goToPage', $courses, 2)
        ->assertSee('Gamma')
        ->assertDontSee('Alpha');
});

it('reorders siblings when an item is dropped in its own list', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma');

    Livewire::test(PageTree::class)
        ->call('handleSort', $pages['gamma']->id, 0, (string) pageId('/courses/'))
        ->assertSet('error', null);

    expect(childNamesOf())->toBe(['gamma', 'alpha', 'beta']);
});

it('adds the pager offset to the dropped position', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma', 'delta');
    $courses = pageId('/courses/');

    Livewire::test(PageTree::class, ['perPage' => 2])
        ->call('toggle', $courses)
        ->call('goToPage', $courses, 2)
        ->call('handleSort', $pages['delta']->id, 0, (string) $courses);

    expect(childNamesOf())->toBe(['alpha', 'beta', 'delta', 'gamma']);
});

it('moves a page when it is dropped in another list', function (): void {
    $pages = createCourses('alpha', 'beta');

    Livewire::test(PageTree::class)
        ->call('handleSort', $pages['beta']->id, 0, (string) pageId('/courses/alpha/'));

    expect(lynx()->pages->get($pages['beta']->id)->path('default'))->toBe('courses/alpha/beta');
});

it('shows an error and changes nothing for an invalid drop', function (): void {
    $pages = createCourses('alpha');

    Livewire::test(PageTree::class)
        ->call('handleSort', pageId('/courses/'), 0, (string) $pages['alpha']->id)
        ->assertSet('error', 'A page cannot be moved under itself or its own descendants.')
        ->assertSee('A page cannot be moved under itself or its own descendants.');

    expect(lynx()->pages->get('/courses/')->parentId)->toBe(pageId('/'));
});

it('enters and leaves move mode', function (): void {
    $pages = createCourses('alpha', 'beta');
    $courses = pageId('/courses/');

    Livewire::test(PageTree::class)
        ->call('startMove', $pages['beta']->id)
        ->assertSet('moving', $pages['beta']->id)
        ->call('cancelMove')
        ->assertSet('moving', null)
        ->call('startMove', $pages['beta']->id)
        ->call('handleSort', $pages['beta']->id, 0, (string) $courses)
        ->assertSet('moving', null);
});

it('moves pages up and down with the keyboard', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma');
    $courses = pageId('/courses/');

    $tree = Livewire::test(PageTree::class)->call('moveUp', $pages['gamma']->id, $courses, 2);
    expect(childNamesOf())->toBe(['alpha', 'gamma', 'beta']);

    $tree->call('moveDown', $pages['alpha']->id, $courses, 0);
    expect(childNamesOf())->toBe(['gamma', 'alpha', 'beta']);
});

it('indents a page under its previous sibling and expands it', function (): void {
    $pages = createCourses('alpha', 'beta');

    Livewire::test(PageTree::class)
        ->call('indent', $pages['beta']->id, pageId('/courses/'), 1)
        ->assertSet('expanded', fn (array $expanded): bool => in_array($pages['alpha']->id, $expanded, true));

    expect(childNamesOf('/courses/alpha/'))->toBe(['beta']);
});

it('outdents a page to just after its parent', function (): void {
    $pages = createCourses('alpha', 'beta');
    lynx()->pages->move($pages['beta'], $pages['alpha']);

    Livewire::test(PageTree::class)
        ->call('outdent', $pages['beta']->id, $pages['alpha']->id, 0);

    expect(childNamesOf())->toBe(['alpha', 'beta']);
});

it('toggles status flags', function (): void {
    $pages = createCourses('alpha');

    $tree = Livewire::test(PageTree::class)->call('toggleStatus', $pages['alpha']->id, 'hidden');
    expect(lynx()->pages->get($pages['alpha']->id)->hasStatus(PageStatus::HIDDEN))->toBeTrue();

    $tree->call('toggleStatus', $pages['alpha']->id, 'hidden');
    expect(lynx()->pages->get($pages['alpha']->id)->hasStatus(PageStatus::HIDDEN))->toBeFalse();
});

it('asks for confirmation with the number of pages before moving them to the trash', function (): void {
    $pages = createCourses('alpha', 'beta');
    lynx()->pages->move($pages['beta'], $pages['alpha']);
    $courses = pageId('/courses/');

    Livewire::test(PageTree::class)
        ->call('toggle', $courses)
        ->call('confirmDelete', $pages['alpha']->id)
        ->assertSet('deleting', $pages['alpha']->id)
        ->assertSee('1 page below')
        ->call('delete', $pages['alpha']->id)
        ->assertSet('deleting', null);

    expect(lynx()->pages->get($pages['alpha']->id)?->parentId)->toBe(lynx()->pages->trashId());
});

it('offers no move or delete for the root and system pages', function (): void {
    $pages = createCourses('alpha');
    DB::table('lynx_pages')->where('id', $pages['alpha']->id)->update(['status' => PageStatus::SYSTEM]);
    $courses = pageId('/courses/');
    $home = pageId('/');

    Livewire::test(PageTree::class)
        ->call('toggle', $courses)
        ->assertSee('Alpha')
        ->assertDontSeeHtml('wire:click="startMove('.$home.')"')
        ->assertDontSeeHtml('wire:click="confirmDelete('.$home.')"')
        ->assertDontSeeHtml('wire:click="startMove('.$pages['alpha']->id.')"')
        ->assertDontSeeHtml('wire:click="confirmDelete('.$pages['alpha']->id.')"')
        ->assertSeeHtml('wire:click="startMove('.$courses.')"');
});

it('starts from a configurable root', function (): void {
    createCourses('alpha');

    Livewire::test(PageTree::class, ['root' => '/courses/'])
        ->assertSee(['Courses', 'Alpha'])
        ->assertDontSee('Home');
});

it('clamps a pager page past the end to the last page', function (): void {
    createCourses('alpha', 'beta', 'gamma');
    $courses = pageId('/courses/');

    Livewire::test(PageTree::class, ['perPage' => 2])
        ->call('toggle', $courses)
        ->call('goToPage', $courses, 999)
        ->assertSee('Gamma')
        ->assertDontSee('Alpha');
});

it('refuses users the admin gate denies', function (): void {
    Gate::define('accessLynxAdmin', fn (): bool => false);

    Livewire::test(PageTree::class)->assertForbidden();
});

it('turns the pager into drop targets in move mode', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma');
    $courses = pageId('/courses/');

    Livewire::test(PageTree::class, ['perPage' => 2])
        ->call('toggle', $courses)
        ->assertDontSeeHtml('wire:sort:group-id="'.$courses.':2"')
        ->call('startMove', $pages['alpha']->id)
        ->assertSeeHtml('wire:sort:group-id="'.$courses.':2"');
});

it('moves a page to the top of another pager page when dropped on its number', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma', 'delta');
    $courses = pageId('/courses/');

    Livewire::test(PageTree::class, ['perPage' => 2])
        ->call('toggle', $courses)
        ->call('startMove', $pages['alpha']->id)
        ->call('handleSort', $pages['alpha']->id, 0, $courses.':2')
        ->assertSet('pages', [$courses => 2])
        ->assertSet('moving', null);

    expect(childNamesOf())->toBe(['beta', 'gamma', 'alpha', 'delta']);
});

it('follows a keyboard move onto the next pager page', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma');
    $courses = pageId('/courses/');

    Livewire::test(PageTree::class, ['perPage' => 2])
        ->call('toggle', $courses)
        ->call('moveDown', $pages['beta']->id, $courses, 1)
        ->assertSet('pages', [$courses => 2]);

    expect(childNamesOf())->toBe(['alpha', 'gamma', 'beta']);
});

it('follows a keyboard move back onto the previous pager page', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma');
    $courses = pageId('/courses/');

    Livewire::test(PageTree::class, ['perPage' => 2])
        ->call('toggle', $courses)
        ->call('goToPage', $courses, 2)
        ->call('moveUp', $pages['gamma']->id, $courses, 2)
        ->assertSet('pages', [$courses => 1]);

    expect(childNamesOf())->toBe(['alpha', 'gamma', 'beta']);
});

it('restores open branches and pager pages from the URL', function (): void {
    createCourses('alpha', 'beta', 'gamma');
    $courses = pageId('/courses/');

    Livewire::withQueryParams(['open' => [pageId('/'), $courses], 'page' => [$courses => 2]])
        ->test(PageTree::class, ['perPage' => 2])
        ->assertSee('Gamma')
        ->assertDontSee('Alpha');
});

it('offers only Unlock on a locked page', function (): void {
    $pages = createCourses('alpha');
    lynx()->pages->setStatus($pages['alpha'], add: ['locked']);
    $id = $pages['alpha']->id;

    Livewire::test(PageTree::class)
        ->call('toggle', pageId('/courses/'))
        ->assertSeeHtml("wire:click=\"toggleStatus({$id}, 'locked')\"")
        ->assertDontSeeHtml("wire:click=\"toggleStatus({$id}, 'hidden')\"")
        ->assertDontSeeHtml("wire:click=\"toggleStatus({$id}, 'unpublished')\"");
});

it('keeps a branch with a locked page deep inside, until that page is unlocked from the tree', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    $lesson = lynx()->pages->new('course', '/courses/alpha/');
    $lesson->name = 'lesson';
    $lesson->title = 'Lesson';
    $lesson->save();
    lynx()->pages->setStatus($lesson, add: ['locked']);

    $tree = Livewire::test(PageTree::class)->call('toggle', pageId('/courses/'))->call('toggle', $alpha->id);

    // Deleting the parent is refused and nothing is deleted.
    $tree->call('confirmDelete', $alpha->id)->call('delete', $alpha->id)->assertSee('is locked. Unlock it first.');
    expect(lynx()->pages->get('/courses/alpha/lesson/'))->not->toBeNull();

    // The locked page offers Unlock in the tree; after it, the branch can go.
    $tree->assertSeeHtml("wire:click=\"toggleStatus({$lesson->id}, 'locked')\"")
        ->call('toggleStatus', $lesson->id, 'locked')
        ->call('confirmDelete', $alpha->id)->call('delete', $alpha->id);

    expect(lynx()->pages->get($alpha->id)?->parentId)->toBe(lynx()->pages->trashId());
});

it('shows the core error when a locked page is changed anyway', function (): void {
    $pages = createCourses('alpha');
    lynx()->pages->setStatus($pages['alpha'], add: ['locked']);

    Livewire::test(PageTree::class)
        ->call('toggleStatus', $pages['alpha']->id, 'hidden')
        ->assertSet('error', fn (?string $error): bool => str_contains((string) $error, 'locked'));
});

it('marks pages that have a draft', function (): void {
    $pages = createCourses('alpha', 'beta');
    lynx()->pages->saveDraft($pages['alpha'], ['price' => 1]);

    $html = Livewire::test(PageTree::class)->call('toggle', pageId('/courses/'))->html();

    expect(substr_count($html, 'data-has-draft'))->toBe(1)
        ->and($html)->toContain('title="Has a draft"');
});
