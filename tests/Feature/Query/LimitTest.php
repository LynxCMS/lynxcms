<?php

declare(strict_types=1);

use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\FindPagesTool;

beforeEach(function (): void {
    courseSite();
    config(['lynx.agents.default_limit' => 3, 'lynx.agents.max_limit' => 5]);

    foreach (range(1, 8) as $i) {
        $page = lynx()->pages->new('course', '/courses/');
        $page->name = "course-{$i}";
        $page->title = "Course {$i}";
        $page->save();
    }
});

it('works out the effective limit', function (?int $selector, ?int $max, ?int $default, ?int $expected): void {
    expect(app(PageFinder::class)->effectiveLimit($selector, $max, $default))->toBe($expected);
})->with([
    'no bounds' => [null, null, null, null],
    'selector only' => [50, null, null, 50],
    'capped' => [50, 10, null, 10],
    'under the cap' => [5, 10, null, 5],
    'default when none' => [null, 10, 3, 3],
    'default above the cap' => [null, 10, 30, 10],
    'cap when no default' => [null, 10, null, 10],
    'cap of zero means one' => [50, 0, null, 1],
]);

it('caps a selector limit with maxLimit and keeps counting every match', function (): void {
    $pages = lynx()->pages->find('template=course, limit=1000', maxLimit: 5);

    expect($pages->count())->toBe(5)
        ->and($pages->total())->toBe(8)
        ->and(lynx()->pages->find('template=course')->count())->toBe(8); // no cap unless asked
});

it('caps the agent toolkit whatever the selector or argument asks for', function (string $selector, ?int $limit, int $returned): void {
    $result = app(AgentToolkit::class)->findPages($selector, limit: $limit);

    expect($result['returned'])->toBe($returned)
        ->and($result['total'])->toBe(8)
        ->and($result['pages'])->toHaveCount($returned);
})->with([
    'default' => ['template=course', null, 3],
    'selector asks for more than the max' => ['template=course, limit=1000', null, 5],
    'selector asks for less' => ['template=course, limit=2', null, 2],
    'argument asks for more than the max' => ['template=course', 1000, 5],
    'argument below the selector' => ['template=course, limit=4', 2, 2],
    'argument of zero' => ['template=course', 0, 1],
    'paging with start' => ['template=course, start=6, limit=5', null, 2],
]);

it('caps the MCP find_pages tool', function (): void {
    LynxServer::tool(FindPagesTool::class, ['selector' => 'template=course, limit=1000', 'limit' => 1000])
        ->assertOk()
        ->assertSee('"returned":5')
        ->assertSee('"total":8');
});
