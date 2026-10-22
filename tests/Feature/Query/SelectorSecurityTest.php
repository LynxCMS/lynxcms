<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Core\Support\Tables;

/*
 * Selectors are the one place where Lynx assembles SQL at runtime (Query\Sql::trusted). These
 * tests feed hostile input through every path that reaches that SQL and check that it is only
 * ever treated as data: no SQL errors, no extra rows, no damage.
 */

const PAYLOADS = [
    "x' OR '1'='1",
    "x') OR ('1'='1",
    "'; DROP TABLE lynx_pages; --",
    "\\' OR 1=1 --",
    '") OR 1=1 --',
    '1 OR 1=1',
    "1' UNION SELECT id FROM lynx_languages --",
    "' || (SELECT name FROM lynx_fields LIMIT 1) || '",
    "delivery'/*",
    "\0",
];

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('code', 'text');
    lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], 'summary', 'body', 'price', 'related', 'code']]);

    foreach ([['delivery', 'Delivery Guide', 249, 'PM-1'], ['agile', 'Agile 100% practical', 99, 'AG_1']] as [$name, $title, $price, $code]) {
        $page = lynx()->pages->new('course', '/courses/');
        $page->name = $name;
        $page->title = $title;
        $page->price = $price;
        $page->code = $code;
        $page->save();
    }
});

$tables = fn (): array => [
    DB::table(Tables::PAGES)->count(),
    DB::table(Tables::LANGUAGES)->count(),
    DB::table(Tables::FIELDS)->count(),
];

it('treats hostile values as data in every operator and clause', function () use ($tables): void {
    $before = $tables();
    $clauses = ['title', 'summary', 'code', 'name', 'price', 'id', 'created', 'parent', 'has_parent', 'template'];
    $operators = ['=', '!=', '<', '>', '<=', '>=', '%=', '*=', '^=', '$='];

    foreach (PAYLOADS as $payload) {
        $quoted = '"'.str_replace('"', '', $payload).'"';

        foreach ($clauses as $clause) {
            foreach ($operators as $operator) {
                foreach (['default', 'pt'] as $language) {
                    $selector = "template=course, {$clause}{$operator}{$quoted}";

                    try {
                        $found = lynx()->language($language, fn () => lynx()->pages->find($selector)->count());
                    } catch (SelectorException) {
                        continue; // rejected before reaching SQL is fine
                    } catch (QueryException $e) {
                        throw new RuntimeException("SQL error for [{$selector}]: {$e->getMessage()}", $e->getCode(), previous: $e);
                    }

                    // Positive matches can never return more than the two courses; a hostile
                    // value must not widen an equality or LIKE match to every row.
                    if (in_array($operator, ['=', '%=', '*=', '^=', '$='], true) && $clause !== 'template') {
                        expect($found)->toBe(0, "[{$selector}] matched {$found} pages");
                    }
                }
            }
        }
    }

    expect($tables())->toBe($before);
});

it('treats LIKE wildcards in values literally', function (): void {
    expect(lynx()->pages->find('template=course, title%=%')->count())->toBe(1)       // only "100%"
        ->and(lynx()->pages->find('template=course, title%=_')->count())->toBe(0)
        ->and(lynx()->pages->find('template=course, code%=_')->count())->toBe(1)     // only "AG_1"
        ->and(lynx()->pages->find('template=course, code^=PM')->count())->toBe(1)
        ->and(lynx()->pages->find('template=course, code%=\\')->count())->toBe(0);
});

it('never puts field names, sort names or operators from the selector into SQL', function (string $selector): void {
    expect(fn () => lynx()->pages->find($selector))->toThrow(SelectorException::class);
})->with([
    'quote in a field name' => ["title'--=x"],
    'comment in a field name' => ['title/**/=x'],
    'table name as a field' => ['lynx_pages=1'],
    'subquery as a field' => ['(select 1)=1'],
    'unknown operator' => ['title~x'],
    'sort with SQL' => ['sort=title; DROP TABLE lynx_pages'],
    'sort with direction' => ['sort=id desc'],
    'sort by a subquery' => ['sort=(select 1)'],
    'sort on an unknown field' => ['sort=lynx_pages.id'],
]);

it('casts limit and start to integers', function (): void {
    expect(lynx()->pages->find('template=course, limit=1; DROP TABLE lynx_pages')->count())->toBe(1)
        ->and(lynx()->pages->find('template=course, start=abc')->count())->toBe(2);
});

it('rejects field names that are not safe identifiers before any table is created', function (string $name): void {
    expect(fn () => lynx()->fields->add($name, 'text'))->toThrow(ValidationException::class);
})->with([
    'quote' => ["x'; DROP TABLE lynx_pages; --"],
    'space' => ['x y'],
    'dash' => ['x-y'],
    'uppercase' => ['Title'],
    'leading digit' => ['1x'],
    'too long' => [str_repeat('a', 52)],
    'unicode' => ['título'],
]);

it('resolves path lookups through bindings', function (): void {
    expect(lynx()->pages->get("/courses/x' OR 1/"))->toBeNull()
        ->and(lynx()->pages->get("/courses/'; DROP TABLE lynx_pages; --/"))->toBeNull()
        ->and(lynx()->pages->get('/courses/%/'))->toBeNull()
        ->and(lynx()->pages->get('/courses/delivery/'))->not->toBeNull();
});

it('rejects LIKE operators on numeric, date and id columns instead of erroring in the database', function (string $selector): void {
    expect(fn () => lynx()->pages->find($selector))->toThrow(SelectorException::class);
})->with([
    'integer field' => ['price%=1'],
    'page reference' => ['related^=1'],
    'id' => ['id%=1'],
    'date' => ['created$=2026'],
]);

it('rejects NUL bytes, which SQLite would treat as the end of a value', function (): void {
    expect(fn () => lynx()->pages->find("template=course, title%=\0"))->toThrow(SelectorException::class);
});

it('filters by name in a language without fallback on every database', function (): void {
    expect(lynx()->pages->find('template=course, name=delivery')->count())->toBe(1);
});
