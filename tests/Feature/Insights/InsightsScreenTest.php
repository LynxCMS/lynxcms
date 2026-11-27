<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Admin\InsightsScreen;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Tests\Fixtures\Insights\FixedSource;

/*
 * The Insights screen (InsightsScreen): the latest report's open insights, heaviest first, with
 * review, approve, archive and dismiss for insights.review, and the number of open insights on the home screen.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
    $this->source = new FixedSource('fixed', [
        new Insight('Price alpha', 'Popular, and free.', pages: [$this->alpha->id], plan: [
            ['action' => 'pages.save', 'arguments' => ['id' => $this->alpha->id, 'values' => ['price' => 40]]],
        ], evidence: [['label' => 'Views', 'value' => 1200]], weight: 2.0),
        new Insight('Read beta', 'Just so you know.', pages: [$this->beta->id], weight: 5.0),
    ]);
    app()->instance(InsightSourceRegistry::class, new InsightSourceRegistry(app()));
    app(InsightSourceRegistry::class)->add($this->source);
    $this->actingAs(new GenericUser(['id' => 1]));
});

function insightNamed(string $title): StoredInsight
{
    return StoredInsight::query()->get()->sole(fn (StoredInsight $insight): bool => $insight->title->translate() === $title);
}

function actAsInsightUser(array $permissions): void
{
    lynx()->run(SaveRole::class, ['name' => 'insights', 'grants' => array_map(fn (string $name): array => ['permission' => $name], ['admin.access', 'pages.view', ...$permissions])]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['insights']]);
    test()->actingAs(new GenericUser(['id' => 5]));
}

it('opens from the sidebar', function (): void {
    $this->get('/lynx')->assertOk()->assertSee('/lynx/insights', false);
    $this->get('/lynx/insights')->assertOk()->assertSee('data-insights', false);
});

it('refuses users who may not view insights', function (): void {
    actAsInsightUser([]);

    $this->get('/lynx/insights')->assertForbidden();
});

it('keeps checking the admin gate on Livewire updates, which skip the admin routes', function (): void {
    $screen = Livewire::test(InsightsScreen::class);
    Gate::define(AdminServiceProvider::GATE, fn (): bool => false);

    $screen->call('closeReview')->assertForbidden();
});

it('says when there is no report yet, and writes one', function (): void {
    Livewire::test(InsightsScreen::class)
        ->assertSeeHtml('data-insights-empty')
        ->call('generate')
        ->assertDontSeeHtml('data-insights-empty')
        ->assertSeeHtml('data-insight="'.insightNamed('Read beta')->id.'"');
});

it('lists the latest report heaviest first, with evidence and page links', function (): void {
    lynx()->run(GenerateInsights::class);
    $html = Livewire::test(InsightsScreen::class)->html();

    expect(strpos($html, 'data-insight="'.insightNamed('Read beta')->id.'"'))->toBeLessThan(strpos($html, 'data-insight="'.insightNamed('Price alpha')->id.'"'))
        ->and($html)->toContain('data-insight-evidence')
        ->and($html)->toContain('data-insight-page="'.$this->alpha->id.'"');
});

it('reviews a plan as a dry run, then approves it', function (): void {
    lynx()->run(GenerateInsights::class);
    $insight = insightNamed('Price alpha');

    $screen = Livewire::test(InsightsScreen::class)->call('review', $insight->id)
        ->assertSet('reviewing', $insight->id)
        ->assertSeeHtml('data-insight-review');
    expect($screen->get('preview'))->not->toBeEmpty()
        ->and(lynx()->pages->get($this->alpha->id)?->price)->toBeNull();

    $screen->call('approve', $insight->id)->assertSet('reviewing', null);
    expect(lynx()->pages->get($this->alpha->id)?->price)->toBe(40)
        ->and($insight->fresh()?->state)->toBe(InsightState::Approved);
});

it('archives and dismisses insights', function (string $method, InsightState $state): void {
    lynx()->run(GenerateInsights::class);
    $insight = insightNamed('Read beta');

    Livewire::test(InsightsScreen::class)->call($method, $insight->id)
        ->assertDontSeeHtml('data-insight="'.$insight->id.'"')
        ->set('state', $state->value)->assertSeeHtml('data-insight-state="'.$state->value.'"');

    expect($insight->fresh()?->state)->toBe($state);
})->with([
    'archive' => ['archive', InsightState::Archived],
    'dismiss' => ['dismiss', InsightState::Dismissed],
]);

it('offers no decisions to users who may only view insights', function (): void {
    lynx()->run(GenerateInsights::class);
    actAsInsightUser(['insights.view']);

    Livewire::test(InsightsScreen::class)
        ->assertSeeHtml('data-insight=')
        ->assertDontSeeHtml('data-insight-dismiss')
        ->assertDontSeeHtml('data-insights-generate');
});

it('offers the earlier reports to choose from', function (): void {
    $first = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class);
    lynx()->run(GenerateInsights::class);

    Livewire::test(InsightsScreen::class)->assertSeeHtml('<option value="'.$first->id.'"');
});

it('shows the open insights on the home screen', function (): void {
    lynx()->run(GenerateInsights::class);

    $this->get('/lynx')->assertOk()->assertSee('data-home-stat="insights"', false);
});
