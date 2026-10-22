<?php

declare(strict_types=1);

use Laravel\Ai\Tools\Request;
use LynxCms\Ai\Tools\ReadTool;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Agents\AgentToolkit;
use LynxCms\Core\Agents\PageHistory;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\Page;
use LynxCms\Mcp\LynxServer;
use LynxCms\Mcp\Tools\PageHistoryTool;

beforeEach(fn (): Lynx => courseSite());

function linkedCourse(): Page
{
    ['alpha' => $alpha, 'beta' => $beta] = createCourses('alpha', 'beta');
    $beta->related = [$alpha->id];
    $beta->save();
    lynx()->menus()->create('top', 'Top bar');
    lynx()->menus()->saveItem(['menu' => 'top', 'kind' => 'page', 'page' => $alpha->id]);
    $alpha = lynx()->pages->get($alpha->id);
    $alpha->name = 'alpha-2';
    lynx()->actingAs(Actor::user(7), fn (): mixed => $alpha->save());

    return lynx()->pages->get($alpha->id);
}

it('reports old URLs, pages linking to the page, menu items included, and activity', function (): void {
    $alpha = linkedCourse();

    $report = app(PageHistory::class)->for('/courses/alpha-2');

    expect($report['page'])->toBe(['id' => $alpha->id, 'path' => '/courses/alpha-2'])
        ->and($report['redirects'])->toBe([['language' => 'default', 'url' => '/courses/alpha', 'redirects' => true]])
        ->and(array_column($report['linked_from'], 'template'))->toBe(['course', 'menu-item'])
        ->and($report['linked_from'][0])->toMatchArray(['path' => '/courses/beta', 'title' => 'Beta'])
        ->and($report['activity'][0])->toMatchArray(['action' => 'pages.save', 'actor' => ['kind' => 'user', 'id' => '7']])
        ->and($report['activity'][0]['summary'])->toContain('Update page alpha-2');
});

it('limits the activity and reports a missing page', function (): void {
    linkedCourse();

    expect(app(PageHistory::class)->for('alpha-2', activityLimit: 1)['activity'])->toHaveCount(1)
        ->and(app(PageHistory::class)->for('/nope'))->toBeNull();
});

it('is an MCP read tool', function (): void {
    linkedCourse();

    LynxServer::tool(PageHistoryTool::class, ['page' => '/courses/alpha-2'])->assertOk()->assertSee(['/courses/alpha', 'menu-item']);
    LynxServer::tool(PageHistoryTool::class, ['page' => '/nope'])->assertHasErrors(['Page not found.']);
});

it('is a read tool of the built-in agent', function (): void {
    linkedCourse();
    $tool = collect(ReadTool::all(app(AgentToolkit::class)))->first(fn (ReadTool $tool): bool => $tool->name() === 'get_page_history');

    expect($tool->handle(new Request(['page' => '/courses/alpha-2'])))->toContain('"url":"/courses/alpha"');
});

it('lists revisions and whether a draft exists, so agents can restore one', function (): void {
    $alpha = createCourses('alpha')['alpha'];
    $alpha->title = 'Alpha two';
    $alpha->save();
    lynx()->pages->saveDraft($alpha, ['price' => 3]);

    $report = app(PageHistory::class)->for('/courses/alpha');

    expect($report['has_draft'])->toBeTrue()
        ->and($report['revisions'])->toHaveCount(2)
        ->and($report['revisions'][0])->toHaveKeys(['id', 'revision', 'by', 'at']);

    $names = array_keys(app(AgentToolkit::class)->writeTools());
    expect($names)->toContain('pages_restore_revision', 'pages_save_draft', 'pages_publish_draft');
});
