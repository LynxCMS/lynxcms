<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use LynxCms\Admin\EditorTabs;
use LynxCms\Admin\Livewire\PageChildren;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

/*
 * The Children tab of the page editor: a page's children, to search, filter, reorder, publish,
 * hide and trash, one at a time or several at once.
 */

beforeEach(function (): void {
    courseSite();
    $this->actingAs(new GenericUser(['id' => 1]));
    createCourses('alpha', 'beta', 'gamma');
});

function childrenParentId(): int
{
    return (int) lynx()->pages->get('/courses/')->id;
}

function childrenTab(): Testable
{
    return Livewire::test(PageChildren::class, ['pageId' => childrenParentId()]);
}

/** @return list<string> */
function listedChildren(Testable $tab): array
{
    return array_map(fn (Page $page): string => $page->name(), $tab->viewData('children')->all());
}

it('is a tab of pages that have children only', function (): void {
    $tabs = app(EditorTabs::class);

    expect($tabs->for(lynx()->pages->get('/courses/')))->toHaveKey('children')
        ->and($tabs->for(lynx()->pages->get('/courses/alpha/')))->not->toHaveKey('children');
});

it('opens in the page editor', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => childrenParentId()])
        ->set('tab', 'children')
        ->assertSet('tab', 'children')
        ->assertSeeLivewire(PageChildren::class);
});

it('lists the children in their order, unpublished ones too', function (): void {
    lynx()->pages->setStatus('/courses/beta/', add: ['unpublished']);

    expect(listedChildren(childrenTab()))->toBe(['alpha', 'beta', 'gamma']);
});

it('finds children by title and by status', function (): void {
    lynx()->pages->setStatus('/courses/gamma/', add: ['hidden']);

    expect(listedChildren(childrenTab()->set('search', 'bet')))->toBe(['beta'])
        ->and(listedChildren(childrenTab()->set('filter', 'hidden')))->toBe(['gamma'])
        ->and(listedChildren(childrenTab()->set('search', 'a "quote, include=all')))->toBe([]);
});

it('reorders children by drag', function (): void {
    $gamma = (int) lynx()->pages->get('/courses/gamma/')->id;

    childrenTab()->call('reorder', $gamma, 0);

    expect(childNamesOf())->toBe(['gamma', 'alpha', 'beta']);
});

it('does not reorder a filtered list, whose positions are not the real ones', function (): void {
    $gamma = (int) lynx()->pages->get('/courses/gamma/')->id;

    $tab = childrenTab()->set('search', 'gam')->call('reorder', $gamma, 0);

    expect($tab->viewData('canReorder'))->toBeFalse();

    expect(childNamesOf())->toBe(['alpha', 'beta', 'gamma']);
});

it('publishes, unpublishes, hides and shows a child', function (): void {
    $beta = (int) lynx()->pages->get('/courses/beta/')->id;
    $tab = childrenTab()->call('toggle', $beta, 'unpublished')->call('toggle', $beta, 'hidden');
    $page = lynx()->pages->get($beta);

    expect($page->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue()
        ->and($page->hasStatus(PageStatus::HIDDEN))->toBeTrue();

    $tab->call('toggle', $beta, 'unpublished');

    expect(lynx()->pages->get($beta)->hasStatus(PageStatus::UNPUBLISHED))->toBeFalse();
});

it('changes the status of the selected children at once', function (): void {
    $ids = array_map(fn (string $path): int => (int) lynx()->pages->get($path)->id, ['/courses/alpha/', '/courses/gamma/']);

    childrenTab()->set('selected', $ids)->call('bulk', 'unpublish')->assertSet('selected', []);

    expect(lynx()->pages->find('parent='.childrenParentId().', status=unpublished')->map(fn (Page $p): string => $p->name()))
        ->toBe(['alpha', 'gamma']);
});

it('moves a child to the trash after asking', function (): void {
    $beta = (int) lynx()->pages->get('/courses/beta/')->id;

    childrenTab()->call('confirmTrash', [$beta])->assertSet('trashing', [$beta])->call('trash');

    expect(childNamesOf())->toBe(['alpha', 'gamma']);
});

it('refuses ids that are not children of the page', function (): void {
    $other = (int) lynx()->pages->get('/courses/')->id;

    childrenTab()->call('toggle', $other, 'hidden');

    expect(lynx()->pages->get($other)->hasStatus(PageStatus::HIDDEN))->toBeFalse();
});

it('pages through many children', function (): void {
    createCourses(...array_map(fn (int $i): string => 'extra-'.$i, range(1, 30)));

    $tab = childrenTab();

    expect($tab->viewData('children')->count())->toBe(PageChildren::PER_PAGE)
        ->and($tab->viewData('total'))->toBe(33)
        ->and($tab->call('goToPage', 2)->viewData('children')->count())->toBe(8);
});

it('sorts every child at once after asking', function (): void {
    lynx()->pages->sort('/courses/gamma/', 0);

    childrenTab()->set('sortBy', 'title_desc')->assertSeeHtml('data-confirm-sort')->call('sortChildren')->assertSet('sortBy', '');

    expect(childNamesOf())->toBe(['gamma', 'beta', 'alpha']);
});

it('ignores a sort it does not offer', function (): void {
    childrenTab()->set('sortBy', 'price')->call('sortChildren');

    expect(childNamesOf())->toBe(['alpha', 'beta', 'gamma']);
});
