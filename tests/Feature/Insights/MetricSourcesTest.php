<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use LynxCms\Contracts\Metrics\PageMetrics;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\Sources\MissingTranslations;
use LynxCms\Insights\Sources\NeglectedPopularPages;
use LynxCms\Tests\Fixtures\FakeTranslationProvider;
use LynxCms\Tests\Fixtures\Insights\FakePageMetrics;

/*
 * Sources with page metrics (ADR-0156): used only when PageMetrics is bound and has min_days of
 * data. Missing translations then ranks pages by views; neglected popular pages needs metrics.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta, 'gamma' => $this->gamma] = createCourses('alpha', 'beta', 'gamma');
    app()->instance(TranslationProvider::class, new FakeTranslationProvider);
});

function bindViews(array $views, ?DateTimeInterface $since = new DateTimeImmutable('-60 days')): void
{
    app()->instance(PageMetrics::class, new FakePageMetrics($views, $since));
}

function neglected(): array
{
    return app(NeglectedPopularPages::class)->insights(InsightPeriod::lastDays(30));
}

it('ranks missing translations by views when metrics are bound', function (): void {
    bindViews([$this->beta->id => 900, $this->gamma->id => 300, $this->alpha->id => 10]);

    [$insight] = app(MissingTranslations::class)->insights(InsightPeriod::lastDays(30));

    expect($insight->pages)->toBe([$this->beta->id, $this->gamma->id, $this->alpha->id])
        ->and($insight->evidence[1]['value'])->toBe(1210)
        ->and($insight->weight)->toBe(1213.0);
});

it('ranks by links while the metrics are younger than the minimum days', function (): void {
    bindViews([$this->gamma->id => 900], new DateTimeImmutable('-5 days'));

    [$insight] = app(MissingTranslations::class)->insights(InsightPeriod::lastDays(30));

    expect($insight->pages)->toBe([$this->alpha->id, $this->beta->id, $this->gamma->id]);
});

it('reports popular pages nobody changed in a year', function (): void {
    Carbon::setTestNow(now()->addMonths(13));
    lynx()->run(SavePage::class, ['id' => $this->gamma->id, 'values' => ['price' => 5]]);
    bindViews([$this->alpha->id => 500, $this->beta->id => 50, $this->gamma->id => 900]);

    [$insight] = neglected();

    expect($insight->pages)->toBe([$this->alpha->id, $this->beta->id])
        ->and($insight->plan)->toBe([])
        ->and($insight->evidence[0]['value'])->toBe(550)
        ->and($insight->subject)->toBe('neglected');
});

it('reports no neglected pages without metrics', function (): void {
    Carbon::setTestNow(now()->addMonths(13));

    expect(neglected())->toBe([]);
});

it('reports no neglected pages while the metrics are younger than the minimum days', function (): void {
    Carbon::setTestNow(now()->addMonths(13));
    bindViews([$this->alpha->id => 500], now()->subDays(5));

    expect(neglected())->toBe([]);
});
