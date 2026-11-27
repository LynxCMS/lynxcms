<?php

declare(strict_types=1);

use LynxCms\Contracts\Actions\Actor;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Insights\Actions\ApplyInsightStep;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Jobs\ApplyStep;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\PlanSteps;
use LynxCms\Tests\Fixtures\FakeTranslationProvider;
use LynxCms\Tests\Fixtures\Insights\FixedSource;

/*
 * A step that fails for a reason outside Lynx, such as a translation provider that cannot be
 * reached, is tried again by its job before it counts as failed (ADR-0157); a refused step fails
 * at once. A job that gives up marks its step failed, so the insight never stays approving.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha] = createCourses('alpha');
    $this->provider = new FakeTranslationProvider;
    app()->instance(TranslationProvider::class, $this->provider);
    app()->instance(InsightSourceRegistry::class, new InsightSourceRegistry(app()));
    app(InsightSourceRegistry::class)->add(new FixedSource('fixed', [new Insight('Translate alpha', 'Read in Portuguese.', pages: [$this->alpha->id], plan: [
        ['action' => 'pages.translate', 'arguments' => ['page' => $this->alpha->id, 'to' => 'pt', 'draft' => true]],
    ])]));
    $insight = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class)->insights()->firstOrFail();
    // Approving without its jobs: the tests run them one attempt at a time.
    $insight->update(['state' => InsightState::Approving, 'steps' => [['status' => PlanSteps::PENDING]]]);
    $this->insight = $insight;
});

function applyStep(bool $retry): void
{
    lynx()->run(ApplyInsightStep::class, ['insight' => test()->insight->id, 'step' => 0, 'retry' => $retry]);
}

it('leaves the step pending for another try when the provider cannot be reached', function (): void {
    $this->provider->failures = 1;

    expect(fn () => applyStep(retry: true))->toThrow(RuntimeException::class)
        ->and($this->insight->fresh()?->steps)->toBe([['status' => 'pending']])
        ->and($this->insight->fresh()?->state)->toBe(InsightState::Approving);

    applyStep(retry: true);

    expect($this->insight->fresh()?->state)->toBe(InsightState::Approved);
});

it('fails the step on its last try', function (): void {
    $this->provider->failures = 1;

    applyStep(retry: false);

    expect($this->insight->fresh()?->steps)->toBe([['status' => 'failed', 'error' => 'cURL error 28: Operation timed out']])
        ->and($this->insight->fresh()?->state)->toBe(InsightState::Open);
});

it('marks the step failed when its job gives up', function (): void {
    new ApplyStep($this->insight->id, 0, Actor::system())->failed(new RuntimeException('Lost connection to the queue'));

    expect($this->insight->fresh()?->steps)->toBe([['status' => 'failed', 'error' => 'Lost connection to the queue']])
        ->and($this->insight->fresh()?->state)->toBe(InsightState::Open);
});

it('tries a step a few times, waiting longer each time', function (): void {
    $job = new ApplyStep($this->insight->id, 0, Actor::system());

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([30, 120]);
});

it('fails the step instead of the request on the sync queue', function (): void {
    $this->insight->update(['state' => InsightState::Open, 'steps' => null]);
    $this->provider->failures = 1;

    lynx()->run(ApproveInsight::class, ['insight' => $this->insight->id]);

    expect($this->insight->fresh()?->steps)->toBe([['status' => 'failed', 'error' => 'cURL error 28: Operation timed out']]);
});
