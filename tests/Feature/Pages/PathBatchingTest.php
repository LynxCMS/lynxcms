<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\Page;

beforeEach(fn (): Lynx => courseSite());

/**
 * Number of queries on the paths table while running a callback.
 */
function pathQueries(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    DB::disableQueryLog();

    return collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'page_paths'))->count();
}

it('loads the paths of pages found together in one query', function (): void {
    createCourses('a1', 'a2', 'a3', 'a4', 'a5', 'a6');

    $pages = lynx()->pages->find('parent=/courses/');

    $queries = pathQueries(fn () => $pages->map(fn (Page $page): string => $page->url()));

    expect($queries)->toBe(1);
});

it('batches again for another language', function (): void {
    lynx()->pages->get('/courses/')?->setLanguageName('pt', 'cursos')->save();

    foreach (createCourses('a1', 'a2', 'a3') as $name => $course) {
        $course->setLanguageName('pt', "{$name}-pt")->save();
    }

    $pages = lynx()->pages->find('parent=/courses/');

    $queries = pathQueries(function () use ($pages): void {
        $pages->map(fn (Page $page): string => $page->url());
        $pages->map(fn (Page $page): string => $page->localUrl('pt'));
    });

    expect($queries)->toBe(2)
        ->and($pages->first()->localUrl('pt'))->toBe('/pt/cursos/a1-pt');
});

it('looks up no Portuguese path for pages not published in Portuguese', function (): void {
    createCourses('a1', 'a2', 'a3');
    $pages = lynx()->pages->find('parent=/courses/');

    $queries = pathQueries(function () use ($pages): void {
        $pages->map(fn (Page $page): string => $page->url());
        $pages->map(fn (Page $page): string => $page->localUrl('pt'));
    });

    // They link to their default-language URL, which the first lookup already loaded.
    expect($queries)->toBe(1)
        ->and($pages->first()->localUrl('pt'))->toBe('/courses/a1');
});

it('still resolves a page loaded on its own', function (): void {
    createCourses('a1');

    expect(lynx()->pages->get('/courses/a1')->url())->toBe('/courses/a1');
});
