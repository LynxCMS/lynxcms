<?php

declare(strict_types=1);

use LynxCms\Core\Templates\FieldCondition;

/** @param  array<string, mixed>  $values */
function conditionMatches(string $condition, array $values): bool
{
    return FieldCondition::parse($condition)->matches(fn (string $field): mixed => $values[$field] ?? null);
}

it('matches equality, alternatives and inequality like ProcessWire', function (string $condition, array $values, bool $expected): void {
    expect(conditionMatches($condition, $values))->toBe($expected);
})->with([
    'equal' => ['kind=link', ['kind' => 'link'], true],
    'not equal' => ['kind=link', ['kind' => 'page'], false],
    'alternatives' => ['kind=link|section', ['kind' => 'section'], true],
    'none of' => ['kind!=link|section', ['kind' => 'page'], true],
    'none of, failing' => ['kind!=link|section', ['kind' => 'link'], false],
    'every clause' => ['kind=link, price>10', ['kind' => 'link', 'price' => 5], false],
    'every clause, passing' => ['kind=link, price>10', ['kind' => 'link', 'price' => 20], true],
]);

it('compares numbers numerically and text by containment', function (string $condition, array $values, bool $expected): void {
    expect(conditionMatches($condition, $values))->toBe($expected);
})->with([
    'greater' => ['price>99', ['price' => 100], true],
    'not greater as text' => ['price>99', ['price' => '100'], true],
    'at most' => ['price<=99', ['price' => 100], false],
    'contains' => ['title%=risk', ['title' => 'Managing Risk'], true],
    'contains, any case' => ['title%=RISK', ['title' => 'managing risk'], true],
]);

it('treats an empty value as empty and toggles as 1 or 0', function (string $condition, array $values, bool $expected): void {
    expect(conditionMatches($condition, $values))->toBe($expected);
})->with([
    'is empty' => ['summary=', ['summary' => ''], true],
    'is empty when missing' => ['summary=', [], true],
    'is not empty' => ['summary!=', ['summary' => 'x'], true],
    'empty list' => ['related=', ['related' => []], true],
    'toggle on' => ['featured=1', ['featured' => true], true],
    'toggle off' => ['featured=0', ['featured' => false], true],
    'page reference' => ['related=12', ['related' => [3, 12]], true],
]);

it('lists the fields it reads', function (): void {
    expect(FieldCondition::parse('kind=link, price>10, kind!=section')->fields())->toBe(['kind', 'price']);
});

it('rejects conditions it cannot read', function (string $condition): void {
    expect(fn (): FieldCondition => FieldCondition::parse($condition))->toThrow(InvalidArgumentException::class);
})->with(['', 'kind', 'kind==link', 'Bad Name=1', 'kind=link,', 'price>']);
