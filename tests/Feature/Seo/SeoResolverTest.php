<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\SeoData;
use LynxCms\Seo\SeoSchema;

beforeEach(function (): void {
    Storage::fake('public');
    config(['lynx-seo.block_non_production' => false]);
    courseSite();
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
    seoSettings(['seo_site_name' => ['default' => 'Lynx Academy', 'pt' => 'Academia Lynx']]);
});

/** @param array<string, mixed> $values */
function seoSettings(array $values): void
{
    lynx()->run(SavePage::class, ['id' => lynx()->settings(SeoSchema::SETTINGS_TEMPLATE)?->id, 'values' => $values]);
}

/** @param array<string, mixed> $values */
function saveAlpha(array $values, array $more = []): void
{
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'values' => $values] + $more);
}

function seoOf(?Page $page = null, string $language = 'default'): SeoData
{
    return lynx()->language($language, fn (): SeoData => lynx()->seo($page ?? lynx()->pages->get(test()->alphaId)));
}

/** @return array<string, mixed> a stored image entry */
function seoImage(int $pageId, string $field, int $width = 1600, int $height = 900): array
{
    return lynx()->run(StoreMedia::class, ['page' => $pageId, 'field' => $field, 'base64' => base64_encode(jpeg($width, $height)), 'filename' => 'card.jpg'])->value;
}

it('titles a page with the pattern and the site name, in each language', function (): void {
    expect(seoOf()->title)->toBe('Alpha | Lynx Academy')
        ->and(seoOf(language: 'pt')->title)->toBe('Alpha | Academia Lynx');

    seoSettings(['seo_title_pattern' => ['default' => '{site}: {title}']]);

    expect(seoOf()->title)->toBe('Lynx Academy: Alpha');
});

it('uses the page\'s own SEO title as it is', function (): void {
    saveAlpha([SeoSchema::TITLE => ['default' => 'Learn alpha fast', 'pt' => 'Aprenda alfa']]);

    expect(seoOf()->title)->toBe('Learn alpha fast')
        ->and(seoOf(language: 'pt')->title)->toBe('Aprenda alfa');
});

it('leaves the site name out when there is none', function (): void {
    seoSettings(['seo_site_name' => ['default' => null, 'pt' => null]]);

    expect(seoOf()->title)->toBe('Alpha');
});

it('describes a page with its own description, else a content field cut at a word, else the default', function (): void {
    config(['lynx-seo.description_length' => 30]);
    seoSettings(['seo_default_description' => ['default' => 'Courses for everyone.']]);

    expect(seoOf()->description)->toBe('Courses for everyone.');

    saveAlpha(['body' => ['default' => '<p>Learn the <strong>basics</strong> &amp; more of alpha in two weeks.</p>']]);
    expect(seoOf()->description)->toBe('Learn the basics & more of…');

    saveAlpha(['summary' => ['default' => 'Short summary.']]);
    expect(seoOf()->description)->toBe('Short summary.');

    saveAlpha([SeoSchema::DESCRIPTION => ['default' => 'Own words.']]);
    expect(seoOf()->description)->toBe('Own words.');
});

it('points the canonical link at the page in the current language unless one is set', function (): void {
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'names' => ['pt' => 'alfa']]);

    expect(seoOf()->canonical)->toBe('http://localhost/courses/alpha')
        ->and(seoOf(language: 'pt')->canonical)->toBe('http://localhost/pt/cursos/alfa');

    saveAlpha([SeoSchema::CANONICAL => '/courses']);
    expect(seoOf()->canonical)->toBe('http://localhost/courses');

    saveAlpha([SeoSchema::CANONICAL => 'https://elsewhere.test/alpha']);
    expect(seoOf()->canonical)->toBe('https://elsewhere.test/alpha');
});

it('asks robots not to index when the page says so, when hidden, or outside production', function (): void {
    expect(seoOf()->robots)->toBeNull();

    saveAlpha([SeoSchema::NOINDEX => true]);
    expect(seoOf()->robots)->toBe('noindex, follow');

    saveAlpha([SeoSchema::NOINDEX => false], ['status' => ['hidden']]);
    expect(seoOf()->robots)->toBe('noindex, follow');

    config(['lynx-seo.noindex_hidden' => false]);
    expect(seoOf()->robots)->toBeNull();

    config(['lynx-seo.block_non_production' => true]);
    expect(seoOf()->robots)->toBe('noindex, follow');
});

it('shares the page\'s own image, else a configured image field, else the default, at 1200 by 630', function (): void {
    lynx()->fields->add('poster', 'images', settings: ['max_files' => 1]);
    lynx()->templates->update('course', ['add' => ['poster']]);
    config(['lynx-seo.image_fields' => ['poster']]);

    expect(seoOf()->image)->toBeNull();

    $settings = lynx()->settings(SeoSchema::SETTINGS_TEMPLATE);
    seoSettings([SeoSchema::DEFAULT_IMAGE => [['values' => seoImage((int) $settings?->id, SeoSchema::DEFAULT_IMAGE)]]]);
    expect(seoOf()->image)->toMatchArray(['width' => 1200, 'height' => 630])
        ->and(seoOf()->image['url'])->toStartWith('http://localhost/');

    saveAlpha(['poster' => [['values' => seoImage(test()->alphaId, 'poster')]]]);
    $poster = seoOf()->image['url'];
    saveAlpha([SeoSchema::IMAGE => [['values' => seoImage(test()->alphaId, SeoSchema::IMAGE)]]]);

    expect($poster)->not->toBe(seoOf()->image['url'])
        ->and(seoOf()->image['url'])->toContain('card');
});

it('gives the locale, the other languages and the hreflang links', function (): void {
    lynx()->run(SavePage::class, ['id' => test()->alphaId, 'names' => ['pt' => 'alfa']]);
    $seo = seoOf(language: 'pt');

    expect($seo->locale)->toBe('pt_PT')
        ->and($seo->localeAlternates)->not->toContain('pt_PT')->not->toBeEmpty()
        ->and(array_column($seo->alternates, 'href'))->toContain('http://localhost/pt/cursos/alfa', 'http://localhost/courses/alpha')
        ->and($seo->siteName)->toBe('Academia Lynx')
        ->and($seo->type)->toBe('website');
});

it('gives the twitter handle with its at sign', function (): void {
    seoSettings([SeoSchema::TWITTER => 'lynxcms']);

    expect(seoOf()->twitter)->toBe('@lynxcms');
});

it('works for pages whose template has no SEO fields and before anything is installed', function (): void {
    $courses = lynx()->pages->get('/courses');

    expect(seoOf($courses)->title)->toBe('Courses | Lynx Academy')
        ->and(seoOf($courses)->canonical)->toBe('http://localhost/courses');
});
