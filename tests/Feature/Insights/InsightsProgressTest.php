<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Insights\Actions\ApproveInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Admin\InsightsScreen;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Tests\Fixtures\Insights\FixedSource;

/*
 * An approval in progress on the Insights screen (ADR-0157): the running insight shows how many
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

it('shows a running insight\'s progress and refreshes until it ends', function (): void {
    Bus::fake();

    Livewire::test(InsightsScreen::class)->call('approve', $this->insight->id)
        ->assertSet('state', 'open')
        ->assertSeeHtml('data-insight-progress="0/2"')
        ->assertSeeHtml('data-insight-running')
        ->assertSeeHtml('data-slot="presence"')
        ->assertSeeHtml('wire:poll');
});

it('does not refresh when nothing is running', function (): void {
    Livewire::test(InsightsScreen::class)->assertDontSeeHtml('wire:poll');
});

it('approves again only what is left', function (): void {
    lynx()->run(ApproveInsight::class, ['insight' => $this->insight->id]);
    Bus::fake();

    Livewire::test(InsightsScreen::class)->call('approve', $this->insight->id);

    expect(StoredInsight::query()->findOrFail($this->insight->id)->steps)->toBe([['status' => 'done'], ['status' => 'pending']]);
});

it('lists each page of a running plan with how far it got', function (): void {
    $this->insight->update(['state' => 'running', 'steps' => [['status' => 'done'], ['status' => 'pending']]]);

    Livewire::test(InsightsScreen::class)
        ->assertSeeHtml('data-insight-step="0" data-step-status="done" data-step-page="'.$this->alpha->id.'"')
        ->assertSeeHtml('data-insight-step="1" data-step-status="pending" data-step-page="'.$this->beta->id.'"');
});

it('lists the page of a failed step with its error', function (): void {
    lynx()->run(ApproveInsight::class, ['insight' => $this->insight->id]);

    Livewire::test(InsightsScreen::class)
        ->assertSeeHtml('data-insight-step="1" data-step-status="failed" data-step-page="'.$this->beta->id.'"');
});

it('opens the details of a failed step, with its trace for superusers', function (): void {
    $this->insight->update(['state' => 'open', 'steps' => [
        ['status' => 'done'],
        ['status' => 'failed', 'error' => 'Timed out', 'exception' => 'RuntimeException', 'trace' => '#0 /app/Provider.php(12)', 'failed_at' => '2026-11-27T10:00:00+00:00', 'tries' => 3],
    ]]);

    Livewire::test(InsightsScreen::class)
        ->assertSeeHtml('data-step-details="1"')
        ->assertSeeHtml('data-step-trace');
});

it('keeps the trace from editors who are not superusers', function (): void {
    $this->insight->update(['state' => 'open', 'steps' => [
        ['status' => 'done'],
        ['status' => 'failed', 'error' => 'Timed out', 'exception' => 'RuntimeException', 'trace' => '#0 /app/Provider.php(12)', 'failed_at' => '2026-11-27T10:00:00+00:00', 'tries' => 3],
    ]]);
    lynx()->run(SaveRole::class, ['name' => 'reviewer', 'grants' => array_map(fn (string $name): array => ['permission' => $name], ['admin.access', 'pages.view', 'insights.view', 'insights.review'])]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['reviewer']]);
    $this->actingAs(new GenericUser(['id' => 5]));

    Livewire::test(InsightsScreen::class)
        ->assertSeeHtml('data-step-details="1"')
        ->assertDontSeeHtml('/app/Provider.php');
});
