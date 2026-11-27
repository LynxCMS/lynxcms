<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Actions\StopInsight;
use LynxCms\Insights\Admin\InsightsScreen;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Jobs\ApplyStep;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Tests\Fixtures\Insights\FixedSource;

/*
 * insights.stop ends a running approval (ADR-0157): the steps done stay done, the waiting ones are
 * stopped and their jobs do nothing, and the insight is open again for another approval.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
    app()->instance(InsightSourceRegistry::class, new InsightSourceRegistry(app()));
    app(InsightSourceRegistry::class)->add(new FixedSource('fixed', [new Insight('Price the courses', 'Free courses sell less.', pages: [$this->alpha->id, $this->beta->id], plan: [
        ['action' => 'pages.save', 'arguments' => ['id' => $this->alpha->id, 'values' => ['price' => 40]]],
        ['action' => 'pages.save', 'arguments' => ['id' => $this->beta->id, 'values' => ['price' => 50]]],
    ])]));
    $this->insight = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class)->insights()->firstOrFail();
    Bus::fake();
    lynx()->run(ApproveInsight::class, ['insight' => $this->insight->id]);
    // The first step ran before the editor stopped the rest.
    new ApplyStep($this->insight->id, 0, Actor::system())->handle();
});

it('stops the waiting steps and reopens the insight', function (): void {
    lynx()->run(StopInsight::class, ['insight' => $this->insight->id]);
    $insight = StoredInsight::query()->findOrFail($this->insight->id);

    expect($insight->state)->toBe(InsightState::Open)
        ->and(array_column($insight->steps ?? [], 'status'))->toBe(['done', 'stopped'])
        ->and(lynx()->pages->get($this->alpha->id)?->price)->toBe(40);
});

it('leaves a stopped step alone when its job runs later', function (): void {
    lynx()->run(StopInsight::class, ['insight' => $this->insight->id]);

    new ApplyStep($this->insight->id, 1, Actor::system())->handle();

    expect(lynx()->pages->get($this->beta->id)?->price)->toBeNull();
});

it('runs the stopped steps when approved again', function (): void {
    lynx()->run(StopInsight::class, ['insight' => $this->insight->id]);

    lynx()->run(ApproveInsight::class, ['insight' => $this->insight->id]);

    expect(array_column(StoredInsight::query()->findOrFail($this->insight->id)->steps ?? [], 'status'))->toBe(['done', 'pending']);
});

it('refuses an insight that is not running', function (): void {
    lynx()->run(StopInsight::class, ['insight' => $this->insight->id]);

    lynx()->run(StopInsight::class, ['insight' => $this->insight->id]);
})->throws(ValidationException::class);

it('changes nothing on a dry run', function (): void {
    lynx()->run(StopInsight::class, ['insight' => $this->insight->id], dryRun: true);

    expect(StoredInsight::query()->findOrFail($this->insight->id)->state)->toBe(InsightState::Running);
});

it('stops from the Insights screen', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(InsightsScreen::class)
        ->assertSeeHtml('data-insight-stop')
        ->call('stop', $this->insight->id)
        ->assertSeeHtml('data-step-status="stopped"');
});

it('reviews only the steps approving again would make', function (): void {
    lynx()->run(StopInsight::class, ['insight' => $this->insight->id]);
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(InsightsScreen::class)->call('review', $this->insight->id)
        ->assertSeeHtml('data-review-step="1"')
        ->assertDontSeeHtml('data-review-step="0"');
});
