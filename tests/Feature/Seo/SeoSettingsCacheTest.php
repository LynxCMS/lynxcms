<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\SeoData;
use LynxCms\Seo\SeoSchema;

/*
 * ADR-0098: the settings page's values every page shares are read once and kept in the cache, per
 * host and language, until the settings page or the schema changes.
 */

beforeEach(function (): void {
    Storage::fake('public');
    config(['lynx-seo.block_non_production' => false]);
    courseSite();
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    createCourses('alpha', 'beta');
    saveSeoSettings([SeoSchema::SITE_NAME => ['default' => 'Lynx Academy', 'pt' => 'Academia Lynx'], SeoSchema::ORG_NAME => 'Lynx Ltd']);
    nextRequest();
});

/** @param array<string, mixed> $values */
function saveSeoSettings(array $values): void
{
    lynx()->run(SavePage::class, ['id' => lynx()->settings(SeoSchema::SETTINGS_TEMPLATE)?->id, 'values' => $values]);
}

/** What a later request starts from: no service kept from this one, only the cache. */
function nextRequest(): void
{
    app()->forgetScopedInstances();
}

function seoFor(string $path, string $language = 'default'): SeoData
{
    return lynx()->language($language, fn (): SeoData => lynx()->seo(lynx()->pages->get($path)));
}

/** Queries reading the settings page's own fields while $read runs. */
function settingsReads(Closure $read): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    DB::disableQueryLog();

    return collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], SeoSchema::SITE_NAME) || str_contains($query['query'], SeoSchema::ORG_NAME))->count();
}

it('reads the settings page once, then takes its values from the cache', function (): void {
    expect(settingsReads(fn (): SeoData => seoFor('/courses/alpha/')))->toBeGreaterThan(0);

    nextRequest();

    expect(settingsReads(fn (): string => seoFor('/courses/beta/')->title))->toBe(0)
        ->and(seoFor('/courses/beta/')->title)->toBe('Beta | Lynx Academy');
});

it('shows a saved setting on the next request', function (): void {
    seoFor('/courses/alpha/');
    nextRequest();
    saveSeoSettings([SeoSchema::SITE_NAME => ['default' => 'Lynx School']]);
    nextRequest();

    expect(seoFor('/courses/alpha/')->title)->toBe('Alpha | Lynx School');
});

it('keeps a copy per language', function (): void {
    seoFor('/courses/alpha/');
    nextRequest();

    expect(seoFor('/courses/alpha/', 'pt')->title)->toBe('Alpha | Academia Lynx');
});

it('reads the settings page again after a field changes', function (): void {
    seoFor('/courses/alpha/');
    nextRequest();
    lynx()->fields->update(SeoSchema::SITE_NAME, ['label' => 'Name of the site']);
    nextRequest();

    expect(settingsReads(fn (): SeoData => seoFor('/courses/alpha/')))->toBeGreaterThan(0);
});

it('keeps a copy per host, since the values hold absolute URLs', function (): void {
    $organizationUrl = fn (): mixed => collect(seoFor('/courses/alpha/')->jsonLd['@graph'])->firstWhere('@type', 'Organization')['url'] ?? null;
    URL::forceRootUrl('http://one.test');
    $one = $organizationUrl();
    nextRequest();
    URL::forceRootUrl('http://two.test');

    expect($one)->toBe('http://one.test/')
        ->and($organizationUrl())->toBe('http://two.test/');
});
