<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\PageStatus;

beforeEach(function (): void {
    courseSite();

    foreach ([
        ['delivery', 'Delivery Guide', 'Guia de Entrega', 249],
        ['agile', 'Agile Practice', null, 99],
        ['risk', 'Risk Management', 'Gestão de Risco', 149],
        ['zeta', 'Zeta Course', null, 500],
    ] as [$name, $title, $pt, $price]) {
        $page = lynx()->pages->new('course', '/courses/');
        $page->name = $name;
        $page->title = $title;
        $page->price = $price;

        if ($pt !== null) {
            $page->setLanguageName('pt', $name.'-pt');
            $page->setLanguageValue('pt', 'title', $pt);
        }

        $page->save();
    }
});

$titles = fn ($pages) => $pages->map(fn ($p) => $p->name('default'));

it('filters shared fields with EXISTS', function () use ($titles): void {
    expect($titles(lynx()->pages->find('template=course, price<200, sort=price')))->toBe(['agile', 'risk']);
});

it('filters translatable fields on the effective value', function () use ($titles): void {
    $default = lynx()->pages->find('template=course, title%=guia');
    $pt = lynx()->language('pt', fn () => lynx()->pages->find('template=course, title%=guia'));
    $ptAgile = lynx()->language('pt', fn () => lynx()->pages->find('template=course, title%=agile'));

    expect($titles($default))->toBe([])
        ->and($titles($pt))->toBe(['delivery'])
        ->and($titles($ptAgile))->toBe(['agile']); // no Portuguese title: falls back to the default one
});

it('sorts on the effective value per language', function () use ($titles): void {
    $pt = lynx()->pages->find('template=course, language=pt, sort=title');

    // Agile Practice, Gestão de Risco, Guia de Entrega, Zeta Course
    expect($titles($pt))->toBe(['agile', 'risk', 'delivery', 'zeta']);
});

it('supports OR values, limit, start and count', function () use ($titles): void {
    $page = lynx()->pages->find('template=course, sort=price, limit=2, start=1');

    expect($titles($page))->toBe(['risk', 'delivery'])
        ->and($page->total())->toBe(4)
        ->and(lynx()->pages->count('price=99|500'))->toBe(2);
});

it('supports parent, has_parent and name', function () use ($titles): void {
    expect(lynx()->pages->count('parent=/courses/'))->toBe(4)
        ->and(lynx()->pages->count('has_parent=/courses/'))->toBe(4)
        ->and($titles(lynx()->pages->find('name=risk-pt, language=pt')))->toBe(['risk']);
});

it('excludes hidden and unpublished pages unless included', function (): void {
    $page = lynx()->pages->get('/courses/zeta/');
    $page->setStatus(PageStatus::HIDDEN);
    $page->save();

    expect(lynx()->pages->count('template=course'))->toBe(3)
        ->and(lynx()->pages->count('template=course, include=hidden'))->toBe(4);
});

it('loads a listing in a constant number of queries', function (): void {
    lynx()->pages->find('template=course'); // warm the language, field and template registries
    app(PageLoader::class)->flush();

    DB::enableQueryLog();
    $pages = lynx()->pages->find('template=course');
    foreach ($pages as $page) {
        $page->price;
    }
    $queries = count(DB::getQueryLog());

    // selector + pages + names + one per field of the template (title, summary, body, price, related)
    expect($queries)->toBeLessThanOrEqual(8);
});
