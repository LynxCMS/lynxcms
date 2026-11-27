<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Admin\InsightsScreen;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Tests\Fixtures\Insights\FixedSource;

/*
 * An approval in progress on the Insights screen (ADR-0157): the approving insight shows how many
 * of its steps ran and the screen refreshes until they end; afterwards the steps that failed are
 * listed with their errors, for another approval.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
    $this->source = new FixedSource('fixed', [new Insight('Price the courses', 'Free courses sell less.', pages: [$this->alpha->id, $this->beta->id], plan: [
        ['action' => 'pages.save', 'arguments' => ['id' => $this->alpha->id, 'values' => ['price' => 40]]],
        ['action' => 'pages.save', 'arguments' => ['id' => $this->beta->id, 'values' => ['price' => 'not a price']]],
    ])]);
    app()->instance(InsightSourceRegistry::class, new InsightSourceRegistry(app()));
    app(InsightSourceRegistry::class)->add($this->source);
    $this->actingAs(new GenericUser(['id' => 1]));
    $this->insight = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class)->insights()->firstOrFail();
});

it('shows an approving insight\'s progress and refreshes until it ends', function (): void {
    Bus::fake();

    Livewire::test(InsightsScreen::class)->call('approve', $this->insight->id)
        ->assertSet('state', 'open')
        ->assertSeeHtml('data-insight-progress="0/2"')
        ->assertSeeHtml('wire:poll');
});

it('does not refresh when nothing is approving', function (): void {
    Livewire::test(InsightsScreen::class)->assertDontSeeHtml('wire:poll');
});

it('lists the steps that failed, with their errors', function (): void {
    lynx()->run(ApproveInsight::class, ['insight' => $this->insight->id]);

    Livewire::test(InsightsScreen::class)
        ->assertSeeHtml('data-insight-failed-step="1"')
        ->assertDontSeeHtml('data-insight-failed-step="0"');
});

it('approves again only what is left', function (): void {
    lynx()->run(ApproveInsight::class, ['insight' => $this->insight->id]);
    Bus::fake();

    Livewire::test(InsightsScreen::class)->call('approve', $this->insight->id);

    expect(StoredInsight::query()->findOrFail($this->insight->id)->steps)->toBe([['status' => 'done'], ['status' => 'pending']]);
});
