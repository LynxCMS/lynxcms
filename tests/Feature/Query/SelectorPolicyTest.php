<?php

declare(strict_types=1);

use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Core\Query\SelectorPolicy;

/*
 * A selector from outside the site's code runs under a policy (SelectorPolicy): a visitor's may
 * only filter the fields the site names and never reach unpublished pages; an editor's or agent's
 * may not use a field its role may not view (ADR-0151), since matches and their order would tell
 * the value.
 */

beforeEach(function (): void {
    courseSite();
    createCourses('alpha', 'beta');
    $alpha = lynx()->pages->get('/courses/alpha/');
    $alpha->price = 120;
    $alpha->summary = 'Secret';
    $alpha->save();
});

function visitorFind(string $selector): PageArray
{
    return lynx()->pages->find($selector, maxLimit: 50, policy: SelectorPolicy::forVisitors(['title', 'price']));
}

it('runs a visitor\'s selector on the fields the site names', function (): void {
    expect(visitorFind('template=course, price>100, sort=-title')->count())->toBe(1);
});

it('refuses a visitor\'s selector that reaches unpublished or draft pages', function (string $selector): void {
    visitorFind($selector);
})->with([
    'include' => ['include=all'],
    'status' => ['status=unpublished'],
    'draft' => ['has_draft=1'],
    'join' => ['join=price'],
    'in a group' => ['(price>1), (include=hidden)'],
])->throws(SelectorException::class);

it('refuses a visitor\'s selector on a field the site does not name', function (string $selector): void {
    visitorFind($selector);
})->with([
    'filter' => ['summary^=S'],
    'sort' => ['sort=-summary'],
    'any of' => ['title|summary%=Secret'],
    'through a reference' => ['related.summary^=S'],
    'search' => ['search*=secret'],
])->throws(SelectorException::class);

it('caps the clauses, values and offset of a visitor\'s selector', function (string $selector): void {
    visitorFind($selector);
})->with([
    'clauses' => [implode(', ', array_fill(0, 11, 'price>1'))],
    'values' => ['price='.implode('|', range(1, 21))],
    'offset' => ['start=5000'],
])->throws(SelectorException::class);

it('refuses an agent\'s selector on a field its role may not view', function (string $selector): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['writer']]);
    lynx()->fields->update('summary', ['settings' => ['access' => ['view' => []]]]);
    lynx()->templates->update('course', ['fields' => [['name' => 'title', 'required' => true], ['name' => 'summary', 'searchable' => true], 'body', 'price', 'related']]);

    app(ActionRunner::class)->actingAs(Actor::mcp(3), fn (): array => app(AgentToolkit::class)->findPages($selector));
})->with([
    'filter' => ['summary^=Se'],
    'sort' => ['sort=summary'],
    'search' => ['search*=secret'],
])->throws(SelectorException::class);

it('runs an agent\'s selector on the fields its role may view', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['writer']]);
    lynx()->fields->update('summary', ['settings' => ['access' => ['view' => []]]]);

    $found = app(ActionRunner::class)->actingAs(Actor::mcp(3), fn (): array => app(AgentToolkit::class)->findPages('price>100'));

    expect($found['total'])->toBe(1);
});
