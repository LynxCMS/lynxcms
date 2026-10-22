<?php

declare(strict_types=1);

use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\PageStatus;

beforeEach(fn (): Lynx => courseSite());

it('counts the children of many parents in one call', function (): void {
    createCourses('alpha', 'beta');
    $courses = lynx()->pages->get('/courses/');
    $home = lynx()->pages->get('/');

    expect(lynx()->pages->childCounts([$home->id, $courses->id, 9999]))
        ->toBe([$home->id => 1, $courses->id => 2, 9999 => 0]);
});

it('applies the status filter of the extra selector', function (): void {
    $pages = createCourses('alpha', 'beta');
    $pages['beta']->status = PageStatus::HIDDEN;
    $pages['beta']->save();
    $courses = (int) lynx()->pages->get('/courses/')->id;

    expect(lynx()->pages->childCounts([$courses]))->toBe([$courses => 1])
        ->and(lynx()->pages->childCounts([$courses], 'include=all'))->toBe([$courses => 2]);
});

it('returns an empty array for no ids', function (): void {
    expect(lynx()->pages->childCounts([]))->toBe([]);
});
