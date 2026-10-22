<?php

declare(strict_types=1);

use LynxCms\Core\Pages\Page;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha');
    $child = lynx()->pages->new('course', '/courses/alpha/');
    $child->name = 'lesson';
    $child->title = 'Lesson';
    $child->save();
});

/**
 * @return list<string>
 */
function foundNames(string $selector): array
{
    return lynx()->pages->find($selector)->map(fn (Page $page): string => $page->name());
}

it('hides every page below an unpublished page from visitors', function (): void {
    lynx()->pages->setStatus('/courses/', add: ['unpublished']);

    $this->get('/courses/')->assertNotFound();
    $this->get('/courses/alpha/')->assertNotFound();
    $this->get('/courses/alpha/lesson/')->assertNotFound();
});

it('leaves pages below an unpublished page out of selectors unless asked', function (): void {
    lynx()->pages->setStatus('/courses/alpha/', add: ['unpublished']);

    expect(foundNames('template=course'))->toBe([])
        ->and(lynx()->pages->count('template=course'))->toBe(0)
        ->and(foundNames('template=course, include=hidden'))->toBe([])
        ->and(foundNames('template=course, include=unpublished'))->toBe(['alpha', 'lesson'])
        ->and(foundNames('template=course, include=all'))->toBe(['alpha', 'lesson']);
});

it('brings the branch back when the page is published again', function (): void {
    lynx()->pages->setStatus('/courses/alpha/', add: ['unpublished']);
    lynx()->pages->setStatus('/courses/alpha/', remove: ['unpublished']);

    $this->get('/courses/alpha/lesson/')->assertOk();
    expect(foundNames('template=course'))->toBe(['alpha', 'lesson']);
});

it('keeps hidden pages reachable and does not hide their children', function (): void {
    lynx()->pages->setStatus('/courses/alpha/', add: ['hidden']);

    $this->get('/courses/alpha/')->assertOk();
    $this->get('/courses/alpha/lesson/')->assertOk();
    expect(foundNames('template=course'))->toBe(['lesson'])
        ->and(foundNames('template=course, include=hidden'))->toBe(['alpha', 'lesson']);
});
