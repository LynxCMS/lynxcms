<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\InsightSource;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\Insights\MissingDescriptions;
use LynxCms\Seo\SeoSchema;

/*
 * The SEO module's insight source: published pages with SEO fields whose description falls back to
 * the site's default, because the page has no description and none of the fields the description
 * is taken from has a value. Registered only when lynxcms/insights is installed.
 */

beforeEach(function (): void {
    courseSite();
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    ['alpha' => $this->alpha, 'beta' => $this->beta, 'gamma' => $this->gamma] = createCourses('alpha', 'beta', 'gamma');
    lynx()->run(SavePage::class, ['id' => $this->beta->id, 'values' => [SeoSchema::DESCRIPTION => ['default' => 'About beta.']]]);
    lynx()->run(SavePage::class, ['id' => $this->gamma->id, 'values' => ['summary' => ['default' => 'Gamma, in short.']]]);
});

function missingDescriptions(): array
{
    return app(MissingDescriptions::class)->insights(InsightPeriod::lastDays(30));
}

it('reports the pages whose description falls back to the site default', function (): void {
    [$insight] = missingDescriptions();

    expect($insight->pages)->toBe([$this->alpha->id])
        ->and($insight->plan)->toBe([])
        ->and($insight->subject)->toBe('missing-descriptions');
});

it('leaves out pages marked noindex', function (): void {
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => [SeoSchema::NOINDEX => true]]);

    expect(missingDescriptions())->toBe([]);
});

it('is registered with the insights module', function (): void {
    $keys = array_map(fn (InsightSource $source): string => $source->key(), app(InsightSourceRegistry::class)->all());

    expect($keys)->toContain('missing-descriptions')
        ->and($keys)->toContain('stale-drafts')
        ->and($keys)->toContain('broken-links');
});
