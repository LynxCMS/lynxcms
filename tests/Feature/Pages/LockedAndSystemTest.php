<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    $lesson = lynx()->pages->new('course', '/courses/alpha/');
    $lesson->name = 'lesson';
    $lesson->title = 'Lesson';
    $lesson->save();
});

function flag(string $path, int $flag): void
{
    DB::table('lynx_pages')->where('id', lynx()->pages->get($path)->id)->update(['status' => $flag]);
    app(ActionRunner::class)->flushCaches();
}

function refuses(Closure $write, string $message): void
{
    expect($write)->toThrow(ValidationException::class, $message);
}

// ----- Locked ------------------------------------------------------------------------------------

it('refuses to save, move, sort, switch the template of or delete a locked page', function (string $action): void {
    flag('/courses/alpha/', PageStatus::LOCKED);
    $page = lynx()->pages->get('/courses/alpha/');

    $write = match ($action) {
        'save' => function () use ($page): void {
            $page->title = 'Changed';
            $page->save();
        },
        'move' => fn () => lynx()->pages->move($page, '/'),
        'sort' => fn () => lynx()->pages->sort($page, 1),
        'switch' => fn () => lynx()->pages->switchTemplate($page, 'courses'),
        'delete' => fn () => lynx()->pages->delete($page),
    };

    refuses($write, 'locked');
    expect((string) lynx()->pages->get('/courses/alpha/')->title)->toBe('Alpha');
})->with(['save', 'move', 'sort', 'switch', 'delete']);

it('refuses to delete a branch that holds a locked page', function (): void {
    flag('/courses/alpha/lesson/', PageStatus::LOCKED);

    refuses(fn () => lynx()->pages->delete('/courses/alpha/'), 'locked');
    expect(lynx()->pages->get('/courses/alpha/lesson/'))->not->toBeNull();
});

it('only lets the lock itself change on a locked page', function (): void {
    flag('/courses/alpha/', PageStatus::LOCKED);

    refuses(fn () => lynx()->pages->setStatus('/courses/alpha/', add: ['hidden']), 'locked');

    lynx()->pages->setStatus('/courses/alpha/', remove: ['locked']);
    $page = lynx()->pages->get('/courses/alpha/');
    $page->title = 'Edited after unlocking';
    $page->save();

    expect((string) lynx()->pages->get('/courses/alpha/')->title)->toBe('Edited after unlocking');
});

it('still allows children, moves into it and sorting siblings of a locked page', function (): void {
    flag('/courses/alpha/', PageStatus::LOCKED);

    $child = lynx()->pages->new('course', '/courses/alpha/');
    $child->name = 'new-child';
    $child->title = 'New child';
    $child->save();
    lynx()->pages->move('/courses/beta/', '/courses/alpha/');

    expect(childNamesOf('/courses/alpha/'))->toBe(['lesson', 'new-child', 'beta']);
});

it('does not unlock a page when its object is saved with the flag removed', function (): void {
    flag('/courses/alpha/', PageStatus::LOCKED);
    $page = lynx()->pages->get('/courses/alpha/');
    $page->setStatus(PageStatus::LOCKED, false);

    refuses(fn () => $page->save(), 'locked');
});

// ----- System ------------------------------------------------------------------------------------

it('refuses to delete, move, switch the template of or change the status of a system page', function (string $action): void {
    flag('/courses/alpha/', PageStatus::SYSTEM);
    $page = lynx()->pages->get('/courses/alpha/');

    $write = match ($action) {
        'delete' => fn () => lynx()->pages->delete($page),
        'move' => fn () => lynx()->pages->move($page, '/'),
        'switch' => fn () => lynx()->pages->switchTemplate($page, 'courses'),
        'status' => fn () => lynx()->pages->setStatus($page, add: ['hidden']),
    };

    refuses($write, 'system');
})->with(['delete', 'move', 'switch', 'status']);

it('refuses to delete a branch that holds a system page', function (): void {
    flag('/courses/alpha/lesson/', PageStatus::SYSTEM);

    refuses(fn () => lynx()->pages->delete('/courses/alpha/'), 'system');
});

it('lets system pages be edited and sorted but keeps them system', function (): void {
    flag('/courses/alpha/', PageStatus::SYSTEM);

    lynx()->run(SavePage::class, ['id' => lynx()->pages->get('/courses/alpha/')->id, 'values' => ['title' => 'Renamed'], 'status' => []]);
    lynx()->pages->sort('/courses/alpha/', 1);

    $page = lynx()->pages->get('/courses/alpha/');
    expect((string) $page->title)->toBe('Renamed')
        ->and($page->hasStatus(PageStatus::SYSTEM))->toBeTrue()
        // System pages are left out of default selectors, so list everything.
        ->and(lynx()->pages->find('parent=/courses/, include=all')->map(fn (Page $p): string => $p->name()))->toBe(['beta', 'alpha']);
});

it('does not let a save turn an existing page into a system page', function (): void {
    lynx()->run(SavePage::class, ['id' => lynx()->pages->get('/courses/beta/')->id, 'status' => ['system']]);

    expect(lynx()->pages->get('/courses/beta/')->hasStatus(PageStatus::SYSTEM))->toBeFalse();
});

it('still creates system pages for modules', function (): void {
    $page = lynx()->run(SavePage::class, ['template' => 'courses', 'parent' => '/', 'names' => ['default' => '_module'], 'values' => ['title' => 'Module'], 'status' => ['hidden', 'system']])->value;

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->hasStatus(PageStatus::SYSTEM))->toBeTrue();
});
