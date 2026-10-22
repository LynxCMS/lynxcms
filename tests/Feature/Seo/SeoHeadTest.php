<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\RobotsTxt;
use LynxCms\Seo\SeoSchema;

beforeEach(function (): void {
    config(['lynx-seo.block_non_production' => false]);
    courseSite();
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alpha = $alpha;
});

function seoHead(string $blade = '<x-lynx-seo :page="$page" />'): string
{
    return Blade::render($blade, ['page' => lynx()->pages->get(test()->alpha->id)]);
}

it('renders the title, description, canonical, social and hreflang tags', function (): void {
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => [SeoSchema::DESCRIPTION => ['default' => 'About alpha.']]]);
    $html = seoHead();

    expect($html)->toContain('<title>Alpha</title>')
        ->toContain('<meta name="description" content="About alpha.">')
        ->toContain('<link rel="canonical" href="http://localhost/courses/alpha">')
        ->toContain('<meta property="og:title" content="Alpha">')
        ->toContain('<meta property="og:url" content="http://localhost/courses/alpha">')
        ->toContain('<meta property="og:type" content="website">')
        ->toContain('<meta name="twitter:card" content="summary">')
        ->toContain('hreflang="x-default"')
        ->not->toContain('name="robots"');
});

it('escapes what editors write', function (): void {
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => [SeoSchema::TITLE => ['default' => 'Tom & "Jerry" > all'], SeoSchema::DESCRIPTION => ['default' => '</title><script>x</script>']]]);

    expect(seoHead())->toContain('<title>Tom &amp; &quot;Jerry&quot; &gt; all</title>')
        ->not->toContain('<script>');
});

it('renders the same with the @seo directive and tells robots to stay out when asked', function (): void {
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => [SeoSchema::NOINDEX => true]]);

    expect(seoHead('@seo($page)'))->toBe(seoHead())
        ->and(seoHead())->toContain('<meta name="robots" content="noindex, follow">');
});

it('serves robots.txt that blocks everything outside production', function (): void {
    config(['lynx-seo.block_non_production' => true]);

    $this->get('/robots.txt')->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSeeText("User-agent: *\nDisallow: /", false);
});

it('serves robots.txt with the editors\' lines and the lines modules add', function (): void {
    lynx()->run(SavePage::class, ['id' => lynx()->settings(SeoSchema::SETTINGS_TEMPLATE)?->id, 'values' => [SeoSchema::ROBOTS_EXTRA => "Disallow: /private/\r\n"]]);
    app(RobotsTxt::class)->line(fn (): string => 'Crawl-delay: 2');

    $body = $this->get('/robots.txt')->assertOk()->getContent();

    // The sitemap module adds its line too.
    expect($body)->toBe("User-agent: *\nDisallow:\nDisallow: /private/\n\nSitemap: http://localhost/sitemap.xml\nCrawl-delay: 2\n");
});
