<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    courseSite();
    // Apps often call Date::use(CarbonImmutable::class), so now() returns immutable dates.
    Date::use(CarbonImmutable::class);
});

afterEach(fn () => Date::useDefault());

it('saves pages when the app uses immutable dates', function (): void {
    $page = lynx()->pages->new('course', '/courses/');
    $page->name = 'immutable';
    $page->title = 'Immutable';
    $page->price = 10;
    $page->save();

    $page->title = 'Still immutable';
    $page->save();

    expect((string) lynx()->pages->get('/courses/immutable/')?->title)->toBe('Still immutable');
});
