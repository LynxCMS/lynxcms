<?php

declare(strict_types=1);

use LynxCms\Core\Query\Clause;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Core\Query\SelectorParser;

it('parses clauses, operators and OR values', function (): void {
    $clauses = (new SelectorParser)->parse('template=course|consulting, price<=300, title%=Delivery, sort=-title, limit=10');

    expect(array_map(fn (Clause $c): array => [$c->field, $c->operator, $c->values], $clauses))->toBe([
        ['template', '=', ['course', 'consulting']],
        ['price', '<=', ['300']],
        ['title', '%=', ['Delivery']],
        ['sort', '=', ['-title']],
        ['limit', '=', ['10']],
    ]);
});

it('keeps commas and pipes inside quotes', function (): void {
    $clauses = (new SelectorParser)->parse('title="Hello, world|again", name^=a');

    expect($clauses[0]->values)->toBe(['Hello, world|again'])->and($clauses[1]->operator)->toBe('^=');
});

it('rejects malformed parts', function (): void {
    (new SelectorParser)->parse('title Delivery');
})->throws(SelectorException::class);
