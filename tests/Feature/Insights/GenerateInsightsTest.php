<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use LynxCms\Insights\Actions\ArchiveInsight;
use LynxCms\Insights\Actions\DismissInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\InsightsReported;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\InsightText;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Tests\Fixtures\Insights\FixedSource;

/*
 * insights.generate (ADR-0155): every registered source reports, and the insights are stored in one
 * report, by weight. Dismissed insights stay quiet for a while, archived ones come back with fresh
 * evidence, and open ones from earlier reports are replaced.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
    $this->source = new FixedSource('fixed', [
        new Insight('Shorten alpha', 'Read by few.', pages: [$this->alpha->id], weight: 1.0),
        new Insight('Expand beta', 'Read by many.', pages: [$this->beta->id], weight: 5.0),
    ]);
    // Only the test's source: the module's own would report the course pages too.
    app()->instance(InsightSourceRegistry::class, new InsightSourceRegistry(app()));
    app(InsightSourceRegistry::class)->add($this->source);
});

function generateInsights(array $input = []): InsightReport
{
    return lynx()->run(GenerateInsights::class, $input)->valueAs(InsightReport::class);
}

/** @return list<string> */
function openTitles(InsightReport $report): array
{
    return $report->insights()->where('state', InsightState::Open->value)->orderByDesc('weight')->get()
        ->map(fn (StoredInsight $insight): string => $insight->title->translate())->values()->all();
}

function insightTitled(InsightReport $report, string $title): StoredInsight
{
    return $report->insights()->get()->sole(fn (StoredInsight $insight): bool => $insight->title->translate() === $title);
}

it('stores the insights of every source in one report, heaviest first', function (): void {
    $report = generateInsights();

    expect(openTitles($report))->toBe(['Expand beta', 'Shorten alpha'])
        ->and($report->insights()->first()?->source)->toBe('fixed');
});

it('gives sources the period of the report', function (): void {
    Carbon::setTestNow('2026-11-27 10:00:00');
    $report = generateInsights(['days' => 7]);

    expect($this->source->period?->start->toDateString())->toBe('2026-11-20')
        ->and($this->source->period?->end->toDateString())->toBe('2026-11-27')
        ->and($report->period_start->toDateString())->toBe('2026-11-20');
});

it('records when each page was last changed', function (): void {
    $insight = insightTitled(generateInsights(), 'Shorten alpha');

    expect(array_keys($insight->basis))->toBe([$this->alpha->id]);
});

it('keeps the other sources when one fails, and lists the failure', function (): void {
    app(InsightSourceRegistry::class)->add(new FixedSource('broken', fails: true));
    $report = generateInsights();

    expect(openTitles($report))->toHaveCount(2)
        ->and($report->failures)->toBe([['source' => 'broken', 'error' => 'The broken source broke.']]);
});

it('writes nothing on a dry run', function (): void {
    lynx()->run(GenerateInsights::class, dryRun: true);

    expect(InsightReport::query()->count())->toBe(0)
        ->and(StoredInsight::query()->count())->toBe(0);
});

it('announces the report after it is stored', function (): void {
    Event::fake([InsightsReported::class]);
    $report = generateInsights();

    Event::assertDispatched(InsightsReported::class, fn (InsightsReported $event): bool => $event->report === $report->id);
});

it('replaces the open insights of the earlier report', function (): void {
    $first = insightTitled(generateInsights(), 'Expand beta');
    $second = insightTitled(generateInsights(), 'Expand beta');

    expect($first->fresh()?->state)->toBe(InsightState::Superseded)
        ->and($second->carried_from)->toBe($first->id);
});

it('keeps a dismissed insight quiet', function (): void {
    $insight = insightTitled(generateInsights(), 'Expand beta');
    lynx()->run(DismissInsight::class, ['insight' => $insight->id]);

    expect(openTitles(generateInsights()))->toBe(['Shorten alpha']);
});

it('reports a dismissed insight again once its weight doubled', function (): void {
    $insight = insightTitled(generateInsights(), 'Expand beta');
    lynx()->run(DismissInsight::class, ['insight' => $insight->id]);
    $this->source->insights[1] = new Insight('Expand beta', 'Read by twice as many.', pages: [$this->beta->id], weight: 10.0);

    expect(openTitles(generateInsights()))->toBe(['Expand beta', 'Shorten alpha']);
});

it('reports a dismissed insight again after the quiet days', function (): void {
    config(['lynx-insights.quiet_days' => 30]);
    $insight = insightTitled(generateInsights(), 'Expand beta');
    lynx()->run(DismissInsight::class, ['insight' => $insight->id]);
    Carbon::setTestNow(now()->addDays(31));

    expect(openTitles(generateInsights()))->toContain('Expand beta');
});

it('brings an archived insight back with fresh evidence', function (): void {
    $insight = insightTitled(generateInsights(), 'Expand beta');
    lynx()->run(ArchiveInsight::class, ['insight' => $insight->id]);
    $this->source->insights[1] = new Insight('Expand beta', 'Read by more.', pages: [$this->beta->id], evidence: [['label' => 'Views', 'value' => 900]], weight: 6.0);

    $again = insightTitled(generateInsights(), 'Expand beta');

    expect($again->carried_from)->toBe($insight->id)
        ->and($again->evidence)->toEqual([['label' => new InsightText('Views'), 'value' => 900]])
        ->and($insight->fresh()?->state)->toBe(InsightState::Archived);
});

it('says when there is nothing to suggest', function (): void {
    $this->source->insights = [];

    expect(generateInsights()->insights()->count())->toBe(0);
});

it('refuses a plan that names an action Lynx does not have', function (): void {
    $this->source->insights = [new Insight('Do magic', 'Why not.', plan: [['action' => 'pages.magic', 'arguments' => []]])];
    $report = generateInsights();

    expect($report->insights()->count())->toBe(0)
        ->and($report->failures[0]['source'])->toBe('fixed');
});
