<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Doctor\StuckInsights;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Tests\Fixtures\Insights\FixedSource;

/*
 * lynx:doctor warns about insights that kept running long after they started (ADR-0157): their
 * jobs are not being run, most often because no queue worker is.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $alpha] = createCourses('alpha');
    app()->instance(InsightSourceRegistry::class, new InsightSourceRegistry(app()));
    app(InsightSourceRegistry::class)->add(new FixedSource('fixed', [new Insight('Price alpha', 'Free.', pages: [$alpha->id], plan: [
        ['action' => 'pages.save', 'arguments' => ['id' => $alpha->id, 'values' => ['price' => 40]]],
    ])]));
    $insight = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class)->insights()->firstOrFail();
    Bus::fake();
    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);
});

it('says nothing while an insight has just started running', function (): void {
    expect(app(StuckInsights::class)->run())->toBe([]);
});

it('warns about an insight still running after the configured minutes', function (): void {
    config(['lynx-insights.stuck_minutes' => 30]);
    $this->travel(31)->minutes();

    $findings = app(StuckInsights::class)->run();

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity)->toBe(Severity::Warning);
});

it('runs with lynx:doctor', function (): void {
    $this->travel(31)->minutes();

    $this->artisan('lynx:doctor', ['--json' => true])->expectsOutputToContain('insights-stuck');
});
