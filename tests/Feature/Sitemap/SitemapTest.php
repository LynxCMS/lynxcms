<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\SeoSchema;
use LynxCms\Sitemap\Actions\InstallSitemap;
use LynxCms\Sitemap\SitemapSchema;
use LynxCms\Sitemap\SitemapSettings;

beforeEach(function (): void {
    courseSite();
    $this->courses = createCourses('a', 'b', 'c', 'd', 'e');
    lynx()->run(SavePage::class, ['id' => $this->courses['a']->id, 'names' => ['pt' => 'a-pt']]);
});

/** @return list<string> the <loc> of a sitemap or sitemap index */
function locs(string $xml): array
{
    preg_match_all('#<loc>([^<]+)</loc>#', $xml, $matches);

    return $matches[1];
}

/** @param array<string, mixed> $values */
function sitemapSettings(array $values): void
{
    lynx()->run(InstallSitemap::class);
    lynx()->run(SavePage::class, ['id' => lynx()->settings(SitemapSchema::SETTINGS_TEMPLATE)?->id, 'values' => $values]);
}

it('lists every published page with a URL in each of its languages, with alternates', function (): void {
    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = (string) $response->getContent();

    expect($xml)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">')
        ->toContain('<xhtml:link rel="alternate" hreflang="pt-PT" href="http://localhost/pt/cursos/a-pt"/>')
        ->toContain('<xhtml:link rel="alternate" hreflang="x-default" href="http://localhost/courses/a"/>')
        ->toContain('<lastmod>')
        ->and(locs($xml))->toContain('http://localhost/', 'http://localhost/courses', 'http://localhost/pt/cursos', 'http://localhost/courses/e', 'http://localhost/pt/cursos/a-pt')
        ->and(locs($xml))->not->toContain('http://localhost/pt/cursos/b')
        ->and(simplexml_load_string($xml))->not->toBeFalse();
});

it('splits into parts under an index when the pages pass the limit', function (): void {
    config(['lynx-sitemap.per_file' => 4]);

    $index = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($index)->toContain('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
        ->and(locs($index))->toBe(['http://localhost/sitemap-1.xml', 'http://localhost/sitemap-2.xml', 'http://localhost/sitemap-3.xml', 'http://localhost/sitemap-4.xml'])
        ->and(locs((string) $this->get('/sitemap-2.xml')->assertOk()->getContent()))->toContain('http://localhost/courses/a', 'http://localhost/courses/b')
        ->and(locs((string) $this->get('/sitemap-4.xml')->getContent()))->toBe(['http://localhost/courses/e']);

    $this->get('/sitemap-5.xml')->assertNotFound();
    $this->get('/sitemap-0.xml')->assertNotFound();
});

it('takes the limit from the admin before the config, within the protocol\'s maximum', function (): void {
    config(['lynx-sitemap.per_file' => 4]);
    sitemapSettings([SitemapSchema::PER_FILE => 6]);

    expect(locs((string) $this->get('/sitemap.xml')->getContent()))->toHaveCount(3);

    sitemapSettings([SitemapSchema::PER_FILE => null]);
    config(['lynx-sitemap.per_file' => 999999]);

    expect((string) $this->get('/sitemap.xml')->getContent())->toContain('<urlset')
        ->and(app(SitemapSettings::class)->perFile())->toBe(SitemapSchema::MAX_PER_FILE);
});

it('can make one sitemap per template, each split on its own', function (): void {
    config(['lynx-sitemap.per_file' => 4]);
    sitemapSettings([SitemapSchema::GROUP_BY => 'template']);

    expect(locs((string) $this->get('/sitemap.xml')->getContent()))->toBe([
        'http://localhost/sitemap-course-1.xml', 'http://localhost/sitemap-course-2.xml', 'http://localhost/sitemap-course-3.xml',
        'http://localhost/sitemap-courses-1.xml', 'http://localhost/sitemap-home-1.xml',
    ])
        ->and(locs((string) $this->get('/sitemap-course-3.xml')->assertOk()->getContent()))->toBe(['http://localhost/courses/e']);

    $this->get('/sitemap-1.xml')->assertNotFound();
    $this->get('/sitemap-nope-1.xml')->assertNotFound();
    $this->get('/sitemap-course-4.xml')->assertNotFound();
});

it('leaves out unpublished, hidden and excluded pages', function (): void {
    lynx()->run(SavePage::class, ['id' => $this->courses['b']->id, 'status' => ['unpublished']]);
    lynx()->run(SavePage::class, ['id' => $this->courses['c']->id, 'status' => ['hidden']]);
    $locs = fn (): array => locs((string) $this->get('/sitemap.xml')->getContent());

    expect($locs())->not->toContain('http://localhost/courses/b', 'http://localhost/courses/c');

    config(['lynx-sitemap.include_hidden' => true]);
    expect($locs())->toContain('http://localhost/courses/c');

    config(['lynx-sitemap.exclude_templates' => ['home']]);
    sitemapSettings([SitemapSchema::EXCLUDE => 'courses']);
    expect($locs())->not->toContain('http://localhost/', 'http://localhost/courses')->toContain('http://localhost/courses/a');
});

it('leaves out pages the SEO module marks noindex or points elsewhere', function (): void {
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    lynx()->run(SavePage::class, ['id' => $this->courses['a']->id, 'values' => [SeoSchema::NOINDEX => true]]);
    lynx()->run(SavePage::class, ['id' => $this->courses['b']->id, 'values' => [SeoSchema::CANONICAL => '/courses/c']]);
    lynx()->run(SavePage::class, ['id' => $this->courses['c']->id, 'values' => [SeoSchema::CANONICAL => '/courses/c']]);

    expect(locs((string) $this->get('/sitemap.xml')->getContent()))
        ->not->toContain('http://localhost/courses/a', 'http://localhost/courses/b')
        ->toContain('http://localhost/courses/c');
});

it('serves from the cache until content changes, and answers 304 to a known ETag', function (): void {
    $first = $this->get('/sitemap.xml');
    $etag = (string) $first->headers->get('ETag');

    $this->get('/sitemap.xml', ['If-None-Match' => $etag])->assertStatus(304);

    createCourses('f');

    expect(locs((string) $this->get('/sitemap.xml')->getContent()))->toContain('http://localhost/courses/f');
});

it('adds the sitemap to robots.txt', function (): void {
    config(['lynx-seo.block_non_production' => false]);

    expect((string) $this->get('/robots.txt')->getContent())->toContain('Sitemap: http://localhost/sitemap.xml');
});

it('can be turned off', function (): void {
    config(['lynx-sitemap.enabled' => false]);

    $this->get('/sitemap.xml')->assertNotFound();
});
