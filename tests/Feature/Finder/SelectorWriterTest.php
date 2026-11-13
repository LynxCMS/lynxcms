<?php

declare(strict_types=1);

use LynxCms\Core\Pages\Page;
use LynxCms\Finder\Search\FinderQuery;
use LynxCms\Finder\SelectorWriter;

/*
 * The finder's searches become selectors (SelectorWriter): conditions of a group that matches all
 * become plain clauses, a group that matches any becomes OR alternatives (ADR-0110), and groups
 * combined with "all" become separate named sets while "any" puts them in one set.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('featured', 'toggle');
    lynx()->fields->add('starts_on', 'date');
    lynx()->templates->update('course', ['add' => [['name' => 'featured'], ['name' => 'starts_on']]]);
    $courses = createCourses('alpha', 'beta', 'gamma', 'delta');
    $values = [
        'alpha' => ['price' => 10, 'summary' => 'Risk basics', 'featured' => true, 'starts_on' => '2026-03-01'],
        'beta' => ['price' => 60, 'summary' => 'Advanced "risk"', 'featured' => false, 'starts_on' => '2026-05-10'],
        'gamma' => ['price' => 80, 'related' => $courses['alpha']],
        'delta' => [],
    ];

    foreach ($values as $name => $fields) {
        foreach ($fields as $field => $value) {
            $courses[$name]->{$field} = $value;
        }

        $courses[$name]->save();
    }

    $this->courses = $courses;
});

/**
 * @param  list<array<string, mixed>>  $conditions
 * @return array<string, mixed>
 */
function finderGroup(array $conditions, string $match = 'all'): array
{
    return ['match' => $match, 'conditions' => array_map(fn (array $condition): array => [...['operator' => 'is', 'value' => '', 'to' => '', 'pages' => []], ...$condition], $conditions)];
}

/**
 * @param  list<array<string, mixed>>  $groups
 */
function finderTerms(array $groups, string $match = 'all'): string
{
    return app(SelectorWriter::class)->terms(FinderQuery::fromArray(['match' => $match, 'groups' => $groups]));
}

/**
 * @param  list<array<string, mixed>>  $groups
 * @return list<string>
 */
function finderNames(array $groups, string $match = 'all'): array
{
    $terms = finderTerms($groups, $match);
    $names = lynx()->pages->find('template=course'.($terms === '' ? '' : ", {$terms}"))->map(fn (Page $page): string => $page->name());
    sort($names);

    return $names;
}

it('writes a group that matches all as plain clauses', function (): void {
    $group = finderGroup([['subject' => 'price', 'operator' => 'gte', 'value' => '50'], ['subject' => 'summary', 'operator' => 'contains', 'value' => 'risk']]);

    expect(finderTerms([$group]))->toBe('price>=50, summary%="risk"')
        ->and(finderNames([$group]))->toBe(['beta']);
});

it('writes a group that matches any as alternatives', function (): void {
    $group = finderGroup([['subject' => 'price', 'operator' => 'lt', 'value' => '20'], ['subject' => 'price', 'operator' => 'gt', 'value' => '70']], 'any');

    expect(finderTerms([$group]))->toBe('(price<20), (price>70)')
        ->and(finderNames([$group]))->toBe(['alpha', 'gamma']);
});

it('makes every group match when the groups combine with all', function (): void {
    $cost = finderGroup([['subject' => 'price', 'operator' => 'lt', 'value' => '20'], ['subject' => 'price', 'operator' => 'gt', 'value' => '70']], 'any');
    $text = finderGroup([['subject' => 'summary', 'operator' => 'contains', 'value' => 'risk'], ['subject' => 'related', 'operator' => 'filled']], 'any');

    expect(finderTerms([$cost, $text]))->toBe('group1=(price<20), group1=(price>70), group2=(summary%="risk"), group2=(related!=)')
        ->and(finderNames([$cost, $text]))->toBe(['alpha', 'gamma']);
});

it('lets any group match when the groups combine with any', function (): void {
    $cheapRisk = finderGroup([['subject' => 'price', 'operator' => 'lt', 'value' => '20'], ['subject' => 'summary', 'operator' => 'contains', 'value' => 'risk']]);
    $dear = finderGroup([['subject' => 'price', 'operator' => 'gt', 'value' => '70']]);

    expect(finderTerms([$cheapRisk, $dear], 'any'))->toBe('(price<20, summary%="risk"), (price>70)')
        ->and(finderNames([$cheapRisk, $dear], 'any'))->toBe(['alpha', 'gamma']);
});

it('keeps visitors\' quotes out of the selector', function (): void {
    $group = finderGroup([['subject' => 'summary', 'operator' => 'contains', 'value' => '"risk", include=all']]);

    expect(finderTerms([$group]))->toBe('summary%="risk, include=all"');
});

it('finds blank and filled fields', function (): void {
    expect(finderNames([finderGroup([['subject' => 'summary', 'operator' => 'blank']])]))->toBe(['delta', 'gamma'])
        ->and(finderNames([finderGroup([['subject' => 'price', 'operator' => 'filled']])]))->toBe(['alpha', 'beta', 'gamma']);
});

it('finds switches that are on and off', function (): void {
    expect(finderNames([finderGroup([['subject' => 'featured', 'operator' => 'on']])]))->toBe(['alpha'])
        ->and(finderNames([finderGroup([['subject' => 'featured', 'operator' => 'off']])]))->toBe(['beta', 'delta', 'gamma']);
});

it('finds dates before, after and between two days', function (): void {
    expect(finderNames([finderGroup([['subject' => 'starts_on', 'operator' => 'before', 'value' => '2026-04-01']])]))->toBe(['alpha'])
        ->and(finderNames([finderGroup([['subject' => 'starts_on', 'operator' => 'between', 'value' => '2026-05-10', 'to' => '2026-05-10']])]))->toBe(['beta'])
        ->and(finderNames([finderGroup([['subject' => 'starts_on', 'operator' => 'day', 'value' => '2026-03-01']])]))->toBe(['alpha']);
});

it('finds pages changed in the last days', function (): void {
    expect(finderTerms([finderGroup([['subject' => 'modified', 'operator' => 'last_days', 'value' => '30']])]))->toBe('modified>=-30 days')
        ->and(finderNames([finderGroup([['subject' => 'modified', 'operator' => 'last_days', 'value' => '30']])]))->toBe(['alpha', 'beta', 'delta', 'gamma']);
});

it('finds pages by the pages they refer to, and how many', function (): void {
    $alpha = (int) $this->courses['alpha']->id;

    expect(finderNames([finderGroup([['subject' => 'related', 'operator' => 'is', 'pages' => [$alpha]]])]))->toBe(['gamma'])
        ->and(finderNames([finderGroup([['subject' => 'related', 'operator' => 'at_least', 'value' => '1']])]))->toBe(['gamma']);
});

it('finds pages by template, status and place in the tree', function (): void {
    expect(finderTerms([finderGroup([['subject' => 'template', 'operator' => 'is', 'value' => 'course'], ['subject' => 'status', 'operator' => 'is', 'value' => 'unpublished']])]))->toBe('template=course, status=unpublished')
        ->and(finderTerms([finderGroup([['subject' => 'parent', 'operator' => 'is', 'pages' => [7]]])]))->toBe('has_parent=7');
});

it('leaves out conditions that are not complete or not allowed', function (array $condition): void {
    expect(finderTerms([finderGroup([$condition])]))->toBe('');
})->with([
    'no value' => [['subject' => 'price', 'operator' => 'gte', 'value' => '']],
    'not a number' => [['subject' => 'price', 'operator' => 'gte', 'value' => 'ten']],
    'not a date' => [['subject' => 'starts_on', 'operator' => 'before', 'value' => 'soon']],
    'unknown field' => [['subject' => 'nope', 'operator' => 'is', 'value' => 'x']],
    'operator the field lacks' => [['subject' => 'price', 'operator' => 'contains', 'value' => '1']],
    'is not on a text field' => [['subject' => 'summary', 'operator' => 'not', 'value' => 'Risk']],
    'unknown template' => [['subject' => 'template', 'operator' => 'is', 'value' => 'course, include=all']],
    'unknown status' => [['subject' => 'status', 'operator' => 'is', 'value' => 'system']],
    'no page chosen' => [['subject' => 'related', 'operator' => 'is', 'pages' => []]],
]);
