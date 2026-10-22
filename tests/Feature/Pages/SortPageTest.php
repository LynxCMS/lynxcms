<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Pages\SortPage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Events\PageMoved;
use LynxCms\Core\Events\PageSorted;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\Page;

beforeEach(fn (): Lynx => courseSite());

it('moves a page to the first position among its siblings', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma');

    lynx()->pages->sort($pages['gamma'], 0);

    expect(childNamesOf())->toBe(['gamma', 'alpha', 'beta']);
});

it('moves a page to a middle position', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma', 'delta');

    lynx()->pages->sort($pages['alpha'], 2);

    expect(childNamesOf())->toBe(['beta', 'gamma', 'alpha', 'delta']);
});

it('clamps positions past the end to the last place', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma');

    lynx()->pages->sort($pages['alpha'], 99);

    expect(childNamesOf())->toBe(['beta', 'gamma', 'alpha']);
});

it('renumbers siblings contiguously from zero', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma');
    DB::table('lynx_pages')->where('id', $pages['beta']->id)->update(['sort' => 40]);

    lynx()->pages->sort($pages['gamma'], 1);

    $sorts = DB::table('lynx_pages')->whereIn('id', array_map(fn (Page $p): ?int => $p->id, $pages))
        ->orderBy('sort')->pluck('sort')->map(fn ($sort): int => (int) $sort)->all();

    expect($sorts)->toBe([0, 1, 2])->and(childNamesOf())->toBe(['alpha', 'gamma', 'beta']);
});

it('returns the page with its new sort value', function (): void {
    $pages = createCourses('alpha', 'beta');

    $page = lynx()->pages->sort($pages['beta'], 0);

    expect($page)->toBeInstanceOf(Page::class)->and($page->sort)->toBe(0);
});

it('fires PageSorted and not PageMoved', function (): void {
    $pages = createCourses('alpha', 'beta');
    Event::fake([PageSorted::class, PageMoved::class]);

    lynx()->pages->sort($pages['beta'], 0);

    Event::assertDispatched(PageSorted::class, fn (PageSorted $e): bool => $e->pageId === $pages['beta']->id && $e->position === 0);
    Event::assertNotDispatched(PageMoved::class);
});

it('rejects a negative position', function (): void {
    $pages = createCourses('alpha');

    expect(fn () => lynx()->pages->sort($pages['alpha'], -1))->toThrow(ValidationException::class);
});

it('rejects a missing page', function (): void {
    expect(fn () => lynx()->pages->sort(9999, 0))->toThrow(ValidationException::class);
});

it('rejects sorting the home page', function (): void {
    expect(fn () => lynx()->pages->sort('/', 0))->toThrow(ValidationException::class);
});

it('reports changes on a dry run and writes nothing', function (): void {
    $pages = createCourses('alpha', 'beta');

    $result = lynx()->pages->sort($pages['beta'], 0, dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and($result->changes->count())->toBe(1)
        ->and(childNamesOf())->toBe(['alpha', 'beta']);
});

it('rolls back when a hook halts the action', function (): void {
    $pages = createCourses('alpha', 'beta');
    lynx()->hook(SortPage::class)->after(fn () => throw new ActionHalted('No.'));

    expect(fn () => lynx()->pages->sort($pages['beta'], 0))->toThrow(ActionHalted::class)
        ->and(childNamesOf())->toBe(['alpha', 'beta']);
});
