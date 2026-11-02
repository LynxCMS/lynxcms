<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\Visibility\PageVisibility;

/*
 * A page reference shows visitors only pages they could open: an unpublished or trashed page an
 * editor still references never leaks its title or URL on the site. The admin sees them all.
 */

/** A course below an unpublished page, so it is offline though its own status is published. */
function offlineBranchCourse(): Page
{
    $archive = lynx()->pages->new('courses', '/');
    $archive->name = 'archive';
    $archive->title = 'Archive';
    $archive->save();
    $gone = lynx()->pages->new('course', '/archive/');
    $gone->name = 'gone';
    $gone->title = 'Gone';
    $gone->save();
    lynx()->pages->setStatus($archive, add: ['unpublished']);

    return $gone;
}

/** Queries run to read alpha's references, once its referenced pages are loaded. */
function queriesToReadReferences(): int
{
    $alpha = lynx()->pages->get('/courses/alpha/');
    lynx()->pages->find('template=course, include=all');
    DB::flushQueryLog();
    DB::enableQueryLog();
    $alpha?->related;
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

beforeEach(function (): void {
    courseSite();
    ['alpha' => $alpha, 'soon' => $soon, 'old' => $old, 'beta' => $beta] = createCourses('alpha', 'soon', 'old', 'beta');
    lynx()->pages->setStatus($soon, add: ['unpublished']);
    $alpha->related = [$beta->id, $soon->id, $old->id];
    $alpha->save();
    lynx()->pages->trash($old);
    Route::get('related-titles', fn (): string => implode(',', lynx()->pages->get('/courses/alpha/')?->related->map(fn (Page $page): string => (string) $page->title) ?? []))->middleware('web');
});

it('leaves unpublished and trashed pages out of references on the site', function (): void {
    $this->get('/related-titles')->assertOk()->assertSeeText('Beta')->assertDontSeeText('Soon')->assertDontSeeText('Old');
});

it('keeps every referenced page for the admin and commands', function (): void {
    $titles = app(PageVisibility::class)->withoutRules(fn (): array => lynx()->pages->get('/courses/alpha/')?->related->map(fn (Page $page): string => (string) $page->title) ?? []);

    expect($titles)->toBe(['Beta', 'Soon', 'Old']);
});

it('leaves pages below an unpublished page out of references on the site', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->related = [offlineBranchCourse()->id, lynx()->pages->get('/courses/beta/')?->id];
    $alpha->save();

    $this->get('/related-titles')->assertOk()->assertSeeText('Beta')->assertDontSeeText('Gone');
});

it('checks every referenced page in the same number of queries however many there are', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha/');
    $two = [offlineBranchCourse()->id, lynx()->pages->get('/courses/beta/')?->id];
    $six = [...$two, ...array_map(fn (Page $page): ?int => $page->id, array_values(createCourses('one', 'two', 'three', 'four')))];
    $alpha->related = $two;
    $alpha->save();
    $few = queriesToReadReferences();

    $alpha->related = $six;
    $alpha->save();

    expect(queriesToReadReferences())->toBe($few);
});
