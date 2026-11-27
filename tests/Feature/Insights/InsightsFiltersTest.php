<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Livewire\Livewire;
use LynxCms\Insights\Actions\DismissInsight;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Admin\InsightsScreen;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Tests\Fixtures\Insights\FixedSource;

/*
 * Finding insights on the Insights screen: a search over their wording, source and pages, and
 * filters by state, source and report, kept in the address.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
    app()->instance(InsightSourceRegistry::class, new InsightSourceRegistry(app()));
    app(InsightSourceRegistry::class)->add(
        new FixedSource('pricing', [new Insight('Price alpha', 'Popular, and free.', pages: [$this->alpha->id], weight: 2.0)]),
        new FixedSource('reading', [new Insight('Expand beta', 'Read by many.', pages: [$this->beta->id], weight: 5.0)]),
    );
    $this->actingAs(new GenericUser(['id' => 1]));
});

function shownInsight(string $title): string
{
    $insight = StoredInsight::query()->latest('id')->get()->first(fn (StoredInsight $insight): bool => $insight->title->translate() === $title);

    return 'data-insight="'.$insight?->id.'"';
}

it('searches the wording of the insights', function (): void {
    lynx()->run(GenerateInsights::class);

    Livewire::test(InsightsScreen::class)->set('search', 'popular')
        ->assertSeeHtml(shownInsight('Price alpha'))
        ->assertDontSeeHtml(shownInsight('Expand beta'));
});

it('searches the titles of the pages an insight is about', function (): void {
    lynx()->run(GenerateInsights::class);

    Livewire::test(InsightsScreen::class)->set('search', $this->beta->title)
        ->assertSeeHtml(shownInsight('Expand beta'))
        ->assertDontSeeHtml(shownInsight('Price alpha'));
});

it('shows open insights first, and the others by their state', function (): void {
    lynx()->run(GenerateInsights::class);
    $dismissed = StoredInsight::query()->get()->sole(fn (StoredInsight $insight): bool => $insight->title->translate() === 'Expand beta');
    lynx()->run(DismissInsight::class, ['insight' => $dismissed->id]);

    Livewire::test(InsightsScreen::class)
        ->assertDontSeeHtml(shownInsight('Expand beta'))
        ->set('state', 'dismissed')->assertSeeHtml(shownInsight('Expand beta'))->assertDontSeeHtml(shownInsight('Price alpha'))
        ->set('state', 'all')->assertSeeHtml(shownInsight('Expand beta'))->assertSeeHtml(shownInsight('Price alpha'));
});

it('filters by source', function (): void {
    lynx()->run(GenerateInsights::class);

    Livewire::test(InsightsScreen::class)->set('source', 'reading')
        ->assertSeeHtml(shownInsight('Expand beta'))
        ->assertDontSeeHtml(shownInsight('Price alpha'));
});

it('opens an earlier report', function (): void {
    $first = lynx()->run(GenerateInsights::class)->valueAs(InsightReport::class);
    lynx()->run(GenerateInsights::class);

    Livewire::test(InsightsScreen::class)->set('report', $first->id)->set('state', 'all')
        ->assertSeeHtml('data-insight-state="superseded"')
        ->assertDontSeeHtml('data-insight-state="open"');
});

it('says when nothing matches, and clears the filters', function (): void {
    lynx()->run(GenerateInsights::class);

    Livewire::test(InsightsScreen::class)->set('search', 'nothing like this')
        ->assertSeeHtml('data-insights-no-match')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSeeHtml(shownInsight('Price alpha'));
});

it('ignores a state or report a request made up', function (): void {
    lynx()->run(GenerateInsights::class);

    Livewire::withQueryParams(['state' => 'bogus', 'report' => 999])->test(InsightsScreen::class)
        ->assertSeeHtml(shownInsight('Price alpha'));
});
