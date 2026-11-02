<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
 * The home page is the root of the tree. Its id is looked up once per request and again after
 * an action, since a layout and the SEO module both ask for it on every page.
 */

beforeEach(function (): void {
    courseSite();
});

/** Root page lookups run while $read runs. */
function rootLookups(Closure $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    DB::disableQueryLog();

    return collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '"parent_id" is null'))->count();
}

it('looks the home page up once however often it is asked for', function (): void {
    expect(rootLookups(function (): void {
        lynx()->pages->get('/');
        lynx()->pages->get('/');
    }))->toBe(1)
        ->and(lynx()->pages->get('/')?->template()->name)->toBe('home');
});

it('looks the home page up again after an action', function (): void {
    lynx()->pages->get('/');
    createCourses('alpha');

    expect(rootLookups(fn () => lynx()->pages->get('/')))->toBe(1);
});
