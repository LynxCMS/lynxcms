<?php

declare(strict_types=1);

use Laravel\Ai\Responses\Data\ToolCall;
use LynxCms\Ai\Agents\CmsAgent;
use LynxCms\Ai\Insights\AgentInsights;
use LynxCms\Ai\Insights\InsightsAgent;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\InsightSource;
use LynxCms\Insights\InsightSourceRegistry;

/*
 * The Insights Agent (lynxcms/ai, an Insights source): off unless lynx-insights.ai.enabled, it asks
 * a model what the site could use, then plans each change with the built-in agent in plan mode, so
 * the change runs only when an editor approves it. No test calls a provider.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
    config(['lynx-insights.ai.enabled' => true, 'lynx.ai.plan_seconds' => 60]);
});

function agentInsights(): array
{
    return app(AgentInsights::class)->insights(InsightPeriod::lastDays(30));
}

/** @param  list<array<string, mixed>>  $insights */
function fakeInsights(array $insights): void
{
    InsightsAgent::fake([['insights' => $insights]]);
}

it('is registered with the insights module', function (): void {
    $keys = array_map(fn (InsightSource $source): string => $source->key(), app(InsightSourceRegistry::class)->all());

    expect($keys)->toContain('insights-agent');
});

it('asks nothing while it is off', function (): void {
    config(['lynx-insights.ai.enabled' => false]);
    InsightsAgent::fake();

    expect(agentInsights())->toBe([]);
    InsightsAgent::assertNeverPrompted();
});

it('turns what the model found into insights, with a plan from the built-in agent', function (): void {
    fakeInsights([[
        'title' => 'Alpha has no price', 'reason' => 'Visitors cannot compare it.',
        'pages' => [$this->alpha->id], 'evidence' => [['label' => 'Courses without a price', 'value' => '2']],
        'change' => 'Set the price of the alpha course to 40.',
    ]]);
    CmsAgent::fake([new ToolCall('1', 'pages_save', ['id' => $this->alpha->id, 'values' => ['price' => 40]]), 'Set the price.']);

    [$insight] = agentInsights();

    expect($insight->title->translate())->toBe('Alpha has no price')
        ->and($insight->pages)->toBe([$this->alpha->id])
        ->and($insight->plan)->toHaveCount(1)
        ->and($insight->plan[0]['arguments']['values'])->toBe(['price' => 40])
        ->and(lynx()->pages->get($this->alpha->id)?->price)->toBeNull();
});

it('keeps an insight as information when planning fails', function (): void {
    fakeInsights([['title' => 'Alpha has no price', 'reason' => 'Visitors cannot compare it.', 'pages' => [$this->alpha->id], 'evidence' => [], 'change' => 'Price it.']]);
    CmsAgent::fake(fn (): never => throw new RuntimeException('The provider timed out.'));

    expect(agentInsights()[0]->plan)->toBe([]);
});

it('keeps an insight as information when its plan would delete', function (): void {
    fakeInsights([['title' => 'Beta is empty', 'reason' => 'Nobody reads it.', 'pages' => [$this->beta->id], 'evidence' => [], 'change' => 'Delete beta.']]);
    CmsAgent::fake([new ToolCall('1', 'pages_delete', ['page' => $this->beta->id]), 'Deleted beta.']);

    expect(agentInsights()[0]->plan)->toBe([]);
});

it('leaves out pages that do not exist, and keeps at most the configured insights', function (): void {
    config(['lynx-insights.ai.max' => 1]);
    fakeInsights([
        ['title' => 'One', 'reason' => 'First.', 'pages' => [$this->alpha->id, 999999], 'evidence' => [], 'change' => ''],
        ['title' => 'Two', 'reason' => 'Second.', 'pages' => [], 'evidence' => [], 'change' => ''],
    ]);

    $insights = agentInsights();

    expect($insights)->toHaveCount(1)
        ->and($insights[0]->pages)->toBe([$this->alpha->id]);
});
