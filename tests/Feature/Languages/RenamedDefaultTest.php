<?php

declare(strict_types=1);

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    lynx()->languages->update('default', ['code' => 'en']);
});

it('creates the menus branch after the default language changed its code', function (): void {
    lynx()->menus()->create('top', 'Top');

    expect(lynx()->menus()->page('top'))->not->toBeNull();
});

it('creates the trash after the default language changed its code', function (): void {
    lynx()->pages->trash('/courses/alpha/');

    expect(lynx()->pages->find('template=trash, include=all')->count())->toBe(1);
});

it('schedules in the default language whatever its code', function (): void {
    lynx()->pages->schedule('/courses/alpha/', publishAt: now()->addDay());

    expect(lynx()->pages->schedules(lynx()->pages->get('/courses/alpha/')))->toHaveCount(1);
});
