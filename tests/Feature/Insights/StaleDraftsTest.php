<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use LynxCms\Core\Actions\Pages\SaveDraft;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\Sources\StaleDrafts;

/*
 * The stale drafts source: drafts nobody saved during the report's period, in one insight that
 * only informs, since discarding a draft loses work and publishing it is the editor's call.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta] = createCourses('alpha', 'beta');
    Carbon::setTestNow('2026-10-01 10:00:00');
    lynx()->run(SaveDraft::class, ['page' => $this->alpha->id, 'values' => ['price' => 20]]);
    Carbon::setTestNow('2026-11-25 10:00:00');
    lynx()->run(SaveDraft::class, ['page' => $this->beta->id, 'values' => ['price' => 30]]);
    Carbon::setTestNow('2026-11-27 10:00:00');
});

function staleDrafts(): array
{
    return app(StaleDrafts::class)->insights(InsightPeriod::lastDays(30));
}

it('reports the drafts nobody saved during the period, by their live pages', function (): void {
    [$insight] = staleDrafts();

    expect($insight->pages)->toBe([$this->alpha->id])
        ->and($insight->plan)->toBe([])
        ->and($insight->evidence[0]['value'])->toBe(1)
        ->and($insight->subject)->toBe('drafts');
});

it('reports nothing when every draft was saved during the period', function (): void {
    lynx()->run(SaveDraft::class, ['page' => $this->alpha->id, 'values' => ['price' => 25]]);

    expect(staleDrafts())->toBe([]);
});

it('names the oldest draft\'s age in days', function (): void {
    expect(staleDrafts()[0]->evidence[1]['value'])->toBe(57);
});
