<?php

declare(strict_types=1);

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Query\SelectorException;

/*
 * ADR-0060: clauses over several fields, words in any order (~=) and counts of children and
 * page references.
 */

beforeEach(function (): void {
    courseSite();

    foreach ([['risk', 'Risk basics', 'Managing projects', null], ['agile', 'Agile teams', 'Risk in sprints', 'agile-pt'], ['quality', 'Quality', 'Management of risk', null]] as [$name, $title, $summary, $ptName]) {
        $page = lynx()->pages->new('course', '/courses/');
        $page->name = $name;
        $page->title = $title;
        $page->summary = $summary;
        if ($ptName !== null) {
            $page->setLanguageValue('pt', 'summary', 'Gestão de equipas');
        }
        $page->save();
    }

    $risk = lynx()->pages->get('/courses/risk/');
    $risk->related = [lynx()->pages->get('/courses/agile/')?->id, lynx()->pages->get('/courses/quality/')?->id];
    $risk->save();
});

$names = fn (string $selector, string $language = 'default'): array => lynx()->language($language, fn (): array => lynx()->pages->find($selector.', sort=name')->map(fn (Page $page): string => (string) $page->name));

it('matches any of several fields', function () use ($names): void {
    expect($names('template=course, title|summary%=risk'))->toBe(['agile', 'quality', 'risk'])
        ->and($names('template=course, title|summary%=risk', 'pt'))->toBe(['quality', 'risk'])
        ->and($names('template=course, name|title=quality'))->toBe(['quality']);
});

it('matches none of several fields with !=', function () use ($names): void {
    expect($names('template=course, title|summary!=Quality'))->toBe(['agile', 'risk']);
});

it('refuses to combine reserved names with fields', function (string $selector): void {
    lynx()->pages->find($selector);
})->with(['template=course, title|sort=x', 'template=course, title|search*=x', 'title|limit=3'])->throws(SelectorException::class);

it('finds every word in any order with ~=', function () use ($names): void {
    expect($names('template=course, summary~="risk management"'))->toBe(['quality'])
        ->and($names('template=course, title|summary~="RISK"'))->toBe(['agile', 'quality', 'risk'])
        ->and($names('template=course, summary~="risk missing"'))->toBe([]);
});

it('refuses ~= on numbers', function (): void {
    lynx()->pages->find('template=course, price~=1');
})->throws(SelectorException::class);

it('counts children', function () use ($names): void {
    expect($names('children.count>2'))->toContain('courses')
        ->and($names('template=course, children.count=0'))->toBe(['agile', 'quality', 'risk'])
        ->and($names('template=courses, children.count<3'))->toBe([]);
});

it('counts page references', function () use ($names): void {
    expect($names('template=course, related.count=2'))->toBe(['risk'])
        ->and($names('template=course, related.count=0'))->toBe(['agile', 'quality']);
});

it('refuses to count fields that hold one value', function (): void {
    lynx()->pages->find('template=course, price.count>0');
})->throws(SelectorException::class);

it('matches none of several values with !=', function () use ($names): void {
    expect($names('template=course, title!="Risk basics"|"Agile teams"'))->toBe(['quality'])
        ->and($names('template=course, name!=risk|agile'))->toBe(['quality'])
        ->and($names('template=course, summary!="Managing projects"|nothing'))->toBe(['agile', 'quality']);
});

it('treats a missing shared value as empty', function () use ($names): void {
    $quality = lynx()->pages->get('/courses/quality/');
    $quality->price = 120;
    $quality->save();

    expect($names('template=course, price!='))->toBe(['quality'])
        ->and($names('template=course, price='))->toBe(['agile', 'risk'])
        ->and($names('template=course, price!=|120'))->toBe([]);
});
