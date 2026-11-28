<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\Sources\BrokenLinks;

/*
 * The broken links source: published pages that link to a page visitors cannot open (unpublished,
 * in the trash, or gone), in one insight that only informs, with each link's target.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $this->alpha, 'beta' => $this->beta, 'gamma' => $this->gamma] = createCourses('alpha', 'beta', 'gamma');
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => ['related' => $this->gamma->id]]);
});

function brokenLinks(): array
{
    return app(BrokenLinks::class)->insights(InsightPeriod::lastDays(30));
}

it('reports published pages that link to an unpublished page', function (): void {
    lynx()->pages->setStatus($this->gamma, add: ['unpublished']);

    [$insight] = brokenLinks();

    expect($insight->pages)->toBe([$this->alpha->id])
        ->and($insight->plan)->toBe([])
        ->and($insight->evidence[0]['value'])->toBe(1)
        ->and($insight->subject)->toBe('broken-links');
});

it('reports links to a page in the trash', function (): void {
    lynx()->pages->trash($this->gamma);

    expect(brokenLinks()[0]->pages)->toBe([$this->alpha->id]);
});

it('leaves out links from pages visitors cannot open either', function (): void {
    lynx()->pages->setStatus($this->gamma, add: ['unpublished']);
    lynx()->pages->setStatus($this->alpha, add: ['unpublished']);

    expect(brokenLinks())->toBe([]);
});

it('reports nothing when every link leads to a published page', function (): void {
    expect(brokenLinks())->toBe([]);
});
