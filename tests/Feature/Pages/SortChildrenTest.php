<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Support\Tables;

/*
 * pages.sort-children puts every child of a page in one order at once: by title, or by when
 * they were created, changed or published. System pages stay last, as with pages.sort.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('gamma', 'alpha', 'beta');
});

function backdate(string $name, string $date): void
{
    DB::table(Tables::PAGES)->where('id', lynx()->pages->get("/courses/{$name}/")->id)->update(['created_at' => $date, 'updated_at' => $date]);
}

it('sorts the children by title, A to Z and Z to A', function (): void {
    lynx()->pages->sortChildren('/courses/', 'title');

    expect(childNamesOf())->toBe(['alpha', 'beta', 'gamma']);

    lynx()->pages->sortChildren('/courses/', 'title', descending: true);

    expect(childNamesOf())->toBe(['gamma', 'beta', 'alpha']);
});

it('sorts the children by date, newest first', function (): void {
    backdate('gamma', '2026-01-01 10:00:00');
    backdate('alpha', '2026-03-01 10:00:00');
    backdate('beta', '2026-02-01 10:00:00');

    lynx()->pages->sortChildren('/courses/', 'created', descending: true);

    expect(childNamesOf())->toBe(['alpha', 'beta', 'gamma']);
});

it('sorts unpublished and hidden children with the others', function (): void {
    lynx()->pages->setStatus('/courses/alpha/', add: ['unpublished', 'hidden']);

    lynx()->pages->sortChildren('/courses/', 'title');

    expect(lynx()->pages->get('/courses/')->children('include=all')->map(fn ($page): string => $page->name()))->toBe(['alpha', 'beta', 'gamma']);
});

it('records which pages moved', function (): void {
    $result = lynx()->pages->sortChildren('/courses/', 'title', dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and($result->changes->all())->toHaveCount(1)
        ->and(childNamesOf())->toBe(['gamma', 'alpha', 'beta']);
});

it('refuses an unknown order', function (): void {
    expect(fn () => lynx()->pages->sortChildren('/courses/', 'price'))->toThrow(ValidationException::class);
});

it('keeps the trash last when sorting the home page\'s children', function (): void {
    lynx()->pages->trash('/courses/beta/');

    lynx()->pages->sortChildren('/', 'title', descending: true);

    expect(lynx()->pages->get('/')->children('include=all')->last()?->id)->toBe(lynx()->pages->trashId());
});
