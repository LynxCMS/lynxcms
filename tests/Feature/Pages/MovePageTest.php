<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();

    $archive = lynx()->pages->new('courses', '/');
    $archive->name = 'archive';
    $archive->title = 'Archive';
    $archive->save();
});

/**
 * Create pages under /archive/ in the given order.
 */
function archived(string ...$names): void
{
    foreach ($names as $name) {
        $page = lynx()->pages->new('course', '/archive/');
        $page->name = $name;
        $page->title = ucfirst($name);
        $page->save();
    }
}

/**
 * @return list<int>
 */
function sortValuesOf(string $parent): array
{
    return DB::table('lynx_pages')->where('parent_id', lynx()->pages->get($parent)->id)
        ->orderBy('sort')->pluck('sort')->map(fn ($sort): int => (int) $sort)->all();
}

it('moves a page to a position among its new siblings', function (): void {
    $pages = createCourses('alpha', 'beta');
    archived('old-one', 'old-two');

    $page = lynx()->pages->move($pages['beta'], '/archive/', 1);

    expect(childNamesOf('/archive/'))->toBe(['old-one', 'beta', 'old-two'])
        ->and(sortValuesOf('/archive/'))->toBe([0, 1, 2])
        ->and($page)->toBeInstanceOf(Page::class)
        ->and($page->sort)->toBe(1);
});

it('compacts the old siblings after a move', function (): void {
    $pages = createCourses('alpha', 'beta', 'gamma');

    lynx()->pages->move($pages['alpha'], '/archive/', 0);

    expect(childNamesOf('/courses/'))->toBe(['beta', 'gamma'])
        ->and(sortValuesOf('/courses/'))->toBe([0, 1]);
});

it('still appends to the end when no position is given', function (): void {
    $pages = createCourses('alpha');
    archived('old-one', 'old-two');

    lynx()->pages->move($pages['alpha'], '/archive/');

    expect(childNamesOf('/archive/'))->toBe(['old-one', 'old-two', 'alpha']);
});

it('gives the first child of an empty parent position zero', function (): void {
    $pages = createCourses('alpha');

    $page = lynx()->pages->move($pages['alpha'], '/archive/');

    expect($page->sort)->toBe(0)->and(sortValuesOf('/archive/'))->toBe([0]);
});

it('rejects a move to the current parent and points to pages.sort', function (): void {
    $pages = createCourses('alpha', 'beta');

    expect(fn () => lynx()->pages->move($pages['beta'], '/courses/', 0))
        ->toThrow(ValidationException::class, 'pages.sort');
});

it('rejects a negative position', function (): void {
    $pages = createCourses('alpha');

    expect(fn () => lynx()->pages->move($pages['alpha'], '/archive/', -1))->toThrow(ValidationException::class);
});

it('leaves sibling order untouched on a dry run', function (): void {
    $pages = createCourses('alpha', 'beta');
    archived('old-one');

    lynx()->pages->move($pages['alpha'], '/archive/', 0, dryRun: true);

    expect(childNamesOf('/archive/'))->toBe(['old-one'])
        ->and(childNamesOf('/courses/'))->toBe(['alpha', 'beta']);
});
