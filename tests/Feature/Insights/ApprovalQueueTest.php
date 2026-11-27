<?php

declare(strict_types=1);

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Insights\Actions\ApplyInsightStep;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Actions\RunInsightStep;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Tests\Fixtures\Insights\FixedSource;

/*
 * An approved plan runs on the queue, a job per step (ADR-0157): each step in its own
 * transaction, its outcome kept on the insight, and the last step to finish decides the insight.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
    $this->source = new FixedSource('fixed');
    app()->instance(InsightSourceRegistry::class, new InsightSourceRegistry(app()));
    app(InsightSourceRegistry::class)->add($this->source);
});

/** @param list<array{action: string, arguments: array<string, mixed>}> $plan */
function plannedInsight(array $plan, array $pages = []): StoredInsight
{
    test()->source->insights = [new Insight('Change things', 'Because.', pages: $pages, plan: $plan)];

    return lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class)->insights()->firstOrFail();
}

function savePrice(int $page, mixed $price): array
{
    return ['action' => 'pages.save', 'arguments' => ['id' => $page, 'values' => ['price' => $price]]];
}

it('queues a job per step in one batch and marks the insight approving', function (): void {
    Bus::fake();
    $insight = plannedInsight([savePrice($this->alpha->id, 40), savePrice($this->beta->id, 50)]);

    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2);
    expect($insight->fresh()?->state)->toBe(InsightState::Approving)
        ->and(array_column($insight->fresh()?->steps ?? [], 'status'))->toBe(['pending', 'pending'])
        ->and(lynx()->pages->get($this->alpha->id)?->price)->toBeNull();
});

it('runs every step and approves the insight', function (): void {
    $insight = plannedInsight([savePrice($this->alpha->id, 40), savePrice($this->beta->id, 50)]);

    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);

    expect(lynx()->pages->get($this->alpha->id)?->price)->toBe(40)
        ->and(lynx()->pages->get($this->beta->id)?->price)->toBe(50)
        ->and($insight->fresh()?->state)->toBe(InsightState::Approved)
        ->and(array_column($insight->fresh()?->steps ?? [], 'status'))->toBe(['done', 'done']);
});

it('keeps the steps that worked and reopens the insight with the step that failed', function (): void {
    $insight = plannedInsight([savePrice($this->alpha->id, 40), savePrice($this->beta->id, 'not a price')]);

    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);
    $steps = $insight->fresh()?->steps ?? [];

    expect($insight->fresh()?->state)->toBe(InsightState::Open)
        ->and(array_column($steps, 'status'))->toBe(['done', 'failed'])
        ->and($steps[1]['error'] ?? '')->not->toBe('')
        ->and(lynx()->pages->get($this->alpha->id)?->price)->toBe(40);
});

it('runs only the steps that did not finish when approved again', function (): void {
    $insight = plannedInsight([savePrice($this->alpha->id, 40), savePrice($this->beta->id, 'not a price')], pages: [$this->alpha->id]);
    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);
    Bus::fake();

    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1);
});

it('refuses a step that deletes, alone', function (): void {
    $insight = plannedInsight([savePrice($this->alpha->id, 40), ['action' => 'pages.delete', 'arguments' => ['page' => $this->beta->id]]]);

    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);

    expect(lynx()->pages->get($this->beta->id))->not->toBeNull()
        ->and(lynx()->pages->get($this->alpha->id)?->price)->toBe(40)
        ->and(array_column($insight->fresh()?->steps ?? [], 'status'))->toBe(['done', 'failed']);
});

it('queues nothing on a dry run', function (): void {
    Bus::fake();
    $insight = plannedInsight([savePrice($this->alpha->id, 40)]);

    lynx()->run(ApproveInsight::class, ['insight' => $insight->id], dryRun: true);

    Bus::assertNothingBatched();
    expect($insight->fresh()?->state)->toBe(InsightState::Open);
});

it('runs a step only once', function (): void {
    $insight = plannedInsight([savePrice($this->alpha->id, 40)]);
    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);

    lynx()->run(ApplyInsightStep::class, ['insight' => $insight->id, 'step' => 0]);
})->throws(ValidationException::class);

it('leaves an insight that is approving out of the next report', function (): void {
    Bus::fake();
    $insight = plannedInsight([savePrice($this->alpha->id, 40)], pages: [$this->alpha->id]);
    lynx()->run(ApproveInsight::class, ['insight' => $insight->id]);

    $report = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class);

    expect($report->insights()->count())->toBe(0)
        ->and($insight->fresh()?->state)->toBe(InsightState::Approving);
});

it('keeps the step actions out of the MCP server and the agent', function (): void {
    $tools = app(ActionRegistry::class)->tools();

    expect($tools)->not->toContain(ApplyInsightStep::class)
        ->and($tools)->not->toContain(RunInsightStep::class);
});
