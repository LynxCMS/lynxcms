<?php

declare(strict_types=1);

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Query\Clause;
use LynxCms\Core\Query\OrGroupSet;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Core\Query\SelectorParser;

/*
 * OR groups (ADR-0110): parenthesised groups are alternatives, and a page must match at least one.
 * Groups sharing a name form a set of their own; every set must match. Inside a group, commas
 * mean AND, as they do outside.
 */

beforeEach(function (): void {
    courseSite();
    $courses = createCourses('alpha', 'beta', 'gamma', 'delta');
    $values = [
        'alpha' => ['price' => 10, 'summary' => 'Risk basics'],
        'beta' => ['price' => 60, 'summary' => 'Advanced risk'],
        'gamma' => ['price' => 80, 'related' => $courses['alpha']],
        'delta' => [],
    ];

    foreach ($values as $name => $fields) {
        foreach ($fields as $field => $value) {
            $courses[$name]->{$field} = $value;
        }

        $courses[$name]->save();
    }

    $courses['gamma']->setLanguageValue('pt', 'summary', 'Risco')->save();
});

/**
 * @return list<string>
 */
function namesFound(string $selector): array
{
    $names = lynx()->pages->find($selector.', sort=name')->map(fn (Page $page): string => $page->name());
    sort($names);

    return $names;
}

it('finds pages matching any of the groups', function (): void {
    expect(namesFound('template=course, (price>=70), (summary%=basics)'))->toBe(['alpha', 'gamma']);
});

it('ANDs the clauses inside a group', function (): void {
    expect(namesFound('template=course, (price>=50, summary%=risk), (price<5)'))->toBe(['beta']);
});

it('makes every named set match, each through any of its groups', function (): void {
    $selector = 'template=course, cost=(price<20), cost=(price>70), text=(summary%=risk), text=(related.count>0)';

    expect(namesFound($selector))->toBe(['alpha', 'gamma']);
});

it('keeps the groups apart from the clauses around them', function (): void {
    expect(namesFound('template=course, price!=, (price<20), (price>70)'))->toBe(['alpha', 'gamma']);
});

it('compares translatable fields by the value visitors see, inside a group', function (): void {
    expect(lynx()->language('pt', fn (): array => namesFound('template=course, (summary=Risco), (price=10)')))->toBe(['alpha', 'gamma']);
});

it('matches blank and not blank inside a group', function (): void {
    expect(namesFound('template=course, (price=), (summary%=advanced)'))->toBe(['beta', 'delta']);
});

it('keeps commas and parentheses inside quotes as text', function (): void {
    $delta = lynx()->pages->get('/courses/delta');
    $delta->summary = 'Risk, (and) more';
    $delta->save();

    expect(namesFound('template=course, (summary="Risk, (and) more"), (price=10)'))->toBe(['alpha', 'delta']);
});

it('refuses terms that only make sense for the whole selector inside a group', function (string $term): void {
    namesFound("template=course, ({$term}, price=10)");
})->with(['sort=name', 'limit=2', 'start=1', 'include=all', 'join=summary', 'language=pt', 'search=risk'])
    ->throws(SelectorException::class);

it('refuses groups inside groups and unbalanced parentheses', function (string $selector): void {
    namesFound($selector);
})->with(['template=course, ((price=10), (price=60))', 'template=course, (price=10', 'template=course, ()'])
    ->throws(SelectorException::class);

it('keeps parentheses inside a value as text', function (): void {
    $alpha = lynx()->pages->get('/courses/alpha');
    $alpha->summary = 'Risk (basics)';
    $alpha->save();

    expect(namesFound('template=course, summary%=Risk (basics)'))->toBe(['alpha']);
});

it('still parses plain selectors into clauses', function (): void {
    expect(array_column(array_map(fn (Clause $clause): array => (array) $clause, (new SelectorParser)->parse('template=course, price>10')), 'field'))->toBe(['template', 'price']);
});

it('reads the groups of a selector', function (): void {
    $parsed = (new SelectorParser)->parseSelector('template=course, cost=(price<20), cost=(price>70), (summary=x)');

    expect(array_column(array_map(fn (Clause $clause): array => (array) $clause, $parsed->clauses), 'field'))->toBe(['template'])
        ->and(array_map(fn (OrGroupSet $set): string => $set->name, $parsed->groups))->toBe(['cost', ''])
        ->and(array_map(fn (OrGroupSet $set): int => count($set->alternatives), $parsed->groups))->toBe([2, 1]);
});

it('takes page states inside a group', function (): void {
    lynx()->pages->saveDraft(lynx()->pages->get('/courses/delta'), ['title' => 'Delta again']);

    expect(namesFound('template=course, (has_draft=1), (price=10)'))->toBe(['alpha', 'delta']);
});

it('says why a term cannot sit inside a group', function (): void {
    expect(fn (): array => namesFound('template=course, (sort=name, price=10)'))
        ->toThrow(SelectorException::class, 'cannot sit inside an OR group');
});

it('asks for parseSelector() when parse() meets OR groups', function (): void {
    expect(fn (): array => (new SelectorParser)->parse('(price=10), (price=60)'))->toThrow(SelectorException::class, 'parseSelector');
});
