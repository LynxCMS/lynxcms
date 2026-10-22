<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Query\SelectorException;

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta', 'gamma');

    foreach (['alpha', 'beta', 'gamma'] as $name) {
        $page = lynx()->pages->get("/courses/{$name}");
        $page->summary = ucfirst($name).' summary';
        $page->body = ucfirst($name).' body';
        $page->price = 10;
        lynx()->pages->save($page);
    }

    app()->forgetScopedInstances();
});

/** @return list<string> field tables the closure queried */
function fieldTablesQueried(Closure $run): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    DB::disableQueryLog();

    $tables = [];

    foreach (DB::getQueryLog() as $query) {
        if (preg_match('/from "?[a-z0-9_]*?(lynx_f_[a-z0-9_]+)"?/', $query['query'], $match) === 1) {
            $tables[] = $match[1];
        }
    }

    return $tables;
}

it('loads pages with their title only, and other fields on first read', function (): void {
    $tables = fieldTablesQueried(fn (): PageArray => lynx()->pages->find('template=course'));

    expect($tables)->toBe(['lynx_f_title']);
});

it('loads a field once for every page of the batch on first read', function (): void {
    $courses = lynx()->pages->find('template=course, sort=title');

    $tables = fieldTablesQueried(function () use ($courses): void {
        expect(array_map(fn (Page $page): string => (string) $page->summary, $courses->all()))
            ->toBe(['Alpha summary', 'Beta summary', 'Gamma summary']);
    });

    expect($tables)->toBe(['lynx_f_summary']);
});

it('loads fields marked to load with the page up front', function (): void {
    lynx()->fields->update('summary', ['settings' => ['autoload' => true]]);
    app()->forgetScopedInstances();

    expect(fieldTablesQueried(fn (): PageArray => lynx()->pages->find('template=course')))->toBe(['lynx_f_title', 'lynx_f_summary']);
});

it('loads the fields a selector joins up front', function (string $selector, array $tables): void {
    expect(fieldTablesQueried(fn (): PageArray => lynx()->pages->find($selector)))->toBe($tables);
})->with([
    'join=price' => ['template=course, join=price', ['lynx_f_title', 'lynx_f_price']],
    'join=summary|body' => ['template=course, join=summary|body', ['lynx_f_title', 'lynx_f_summary', 'lynx_f_body']],
    'join=all' => ['template=course, join=all', ['lynx_f_title', 'lynx_f_summary', 'lynx_f_body', 'lynx_f_price', 'lynx_f_related']],
]);

it('refuses to join a field the site does not have', function (): void {
    lynx()->pages->find('template=course, join=nope');
})->throws(SelectorException::class);

it('keeps a value set before the field was read, and saves it with the others intact', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha');
    $alpha->summary = 'New summary';
    lynx()->pages->save($alpha);
    app()->forgetScopedInstances();

    $fresh = lynx()->pages->get('/courses/alpha');

    expect((string) $fresh->summary)->toBe('New summary')
        ->and((string) $fresh->body)->toBe('Alpha body')
        ->and($fresh->price)->toBe(10);
});

it('gives every value to code that reads them all', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha');

    expect($alpha->toArray()['fields']['body']['value'])->toBe('Alpha body')
        ->and($alpha->valuesByLanguage()['default']['summary'])->toBe('Alpha summary')
        ->and(lynx()->pages->get('/courses/beta')->rawValues()['price'][0])->toBe(10);
});

it('loads deferred fields into copies of a page', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha');
    $copy = clone $alpha;

    expect((string) $copy->body)->toBe('Alpha body')
        ->and((string) $alpha->body)->toBe('Alpha body');
});

it('refuses a load with the page setting that is not true or false', function (): void {
    lynx()->fields->update('summary', ['settings' => ['autoload' => 'often']]);
})->throws(ValidationException::class);
