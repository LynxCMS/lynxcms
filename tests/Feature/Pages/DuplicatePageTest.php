<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

/*
 * pages.duplicate copies a page with its values in every language, next to it as "name-copy"
 * and unpublished, or under another parent; with "children" its whole branch comes along, and
 * references between the copied pages point at the copies.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');

    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->summary = 'An intro';
    $alpha->setLanguageValue('pt', 'summary', 'Uma introdução');
    $alpha->setLanguageName('pt', 'alfa');
    $alpha->price = 120;
    $alpha->save();
});

/** @return list<string> every child, unpublished ones too */
function allChildNamesOf(string $path): array
{
    return lynx()->pages->get($path)->children('include=all')->map(fn (Page $page): string => $page->name());
}

function addLesson(string $parent, string $name): Page
{
    $lesson = lynx()->pages->new('course', $parent);
    $lesson->name = $name;
    $lesson->title = ucfirst($name);
    $lesson->save();

    return $lesson;
}

it('copies the page next to it, unpublished, with its values in every language', function (): void {
    $copy = lynx()->pages->duplicate('/courses/alpha/');
    $copy = lynx()->pages->get((int) $copy->id);

    expect($copy->name())->toBe('alpha-copy')
        ->and($copy->name('pt'))->toBe('alfa-copy')
        ->and($copy->parentId)->toBe((int) lynx()->pages->get('/courses/')->id)
        ->and($copy->hasStatus(PageStatus::UNPUBLISHED))->toBeTrue()
        ->and((string) $copy->title)->toBe('Alpha')
        ->and((string) $copy->getLanguageValue('pt', 'summary'))->toBe('Uma introdução')
        ->and($copy->price)->toBe(120)
        ->and(childNamesOf())->toBe(['alpha', 'beta']);
});

it('numbers further copies', function (): void {
    lynx()->pages->duplicate('/courses/alpha/');

    expect(lynx()->pages->duplicate('/courses/alpha/')->name())->toBe('alpha-copy-2');
});

it('copies the children only when asked', function (): void {
    addLesson('/courses/alpha/', 'week-1');

    $bare = lynx()->pages->duplicate('/courses/alpha/');
    $full = lynx()->pages->duplicate('/courses/alpha/', children: true);

    expect(lynx()->pages->get((int) $bare->id)->children('include=all')->count())->toBe(0)
        ->and(allChildNamesOf('/courses/alpha-copy-2/'))->toBe(['week-1'])
        ->and(childNamesOf('/courses/alpha/'))->toBe(['week-1'])
        ->and($full->id)->not->toBe($bare->id);
});

it('keeps the name under another parent where it is free', function (): void {
    $copy = lynx()->pages->duplicate('/courses/alpha/', parent: '/courses/beta/');

    expect($copy->name())->toBe('alpha')
        ->and(childNamesOf('/courses/beta/'))->toBe([])
        ->and(lynx()->pages->get('/courses/beta/')->children('include=all')->count())->toBe(1);
});

it('points references between copied pages at the copies', function (): void {
    $week = addLesson('/courses/alpha/', 'week-1');
    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->related = [(int) $week->id, (int) lynx()->pages->get('/courses/beta/')->id];
    $alpha->save();

    $copy = lynx()->pages->duplicate('/courses/alpha/', children: true);
    $copiedWeek = (int) lynx()->pages->get('/courses/alpha-copy/week-1/')->id;

    expect(lynx()->pages->get((int) $copy->id)->getUnformatted('related'))->toBe([$copiedWeek, (int) lynx()->pages->get('/courses/beta/')->id]);
});

it('refuses the home page', function (): void {
    expect(fn () => lynx()->pages->duplicate('/'))->toThrow(ValidationException::class);
});

it('writes nothing on a dry run', function (): void {
    addLesson('/courses/alpha/', 'week-1');

    $result = lynx()->pages->duplicate('/courses/alpha/', children: true, dryRun: true);

    expect($result)->toBeInstanceOf(ActionResult::class)
        ->and($result->changes->all())->not->toBeEmpty()
        ->and(childNamesOf())->toBe(['alpha', 'beta']);
});

it('refuses to copy a branch under itself', function (): void {
    addLesson('/courses/alpha/', 'week-1');

    expect(fn () => lynx()->pages->duplicate('/courses/alpha/', parent: '/courses/alpha/week-1/', children: true))
        ->toThrow(ValidationException::class);
});

it('writes nothing when a page of the branch cannot be copied', function (): void {
    lynx()->templates->add('syllabus', fields: ['title'], singleton: true);
    $syllabus = lynx()->pages->new('syllabus', '/courses/alpha/');
    $syllabus->name = 'syllabus';
    $syllabus->title = 'Syllabus';
    $syllabus->save();

    expect(fn () => lynx()->pages->duplicate('/courses/alpha/', children: true))->toThrow(ValidationException::class)
        ->and(allChildNamesOf('/courses/'))->toBe(['alpha', 'beta']);
});

it('puts a prefix before the copy\'s title in every language, not its children\'s', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->setLanguageValue('pt', 'title', 'Alfa');
    $alpha->save();
    addLesson('/courses/alpha/', 'week-1');

    $copy = lynx()->pages->duplicate('/courses/alpha/', children: true, titlePrefix: '[Copy]');
    $copy = lynx()->pages->get((int) $copy->id);

    expect((string) $copy->getLanguageValue('default', 'title'))->toBe('[Copy] Alpha')
        ->and((string) $copy->getLanguageValue('pt', 'title'))->toBe('[Copy] Alfa')
        ->and((string) lynx()->pages->get('/courses/alpha-copy/week-1/')->title)->toBe('Week-1');
});
