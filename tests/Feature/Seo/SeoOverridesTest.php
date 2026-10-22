<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use LynxCms\Seo\Actions\EnableSeo;

beforeEach(function (): void {
    config(['lynx-seo.block_non_production' => false]);
    courseSite();
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    createCourses('alpha');
});

it('lets a template or controller override the robots rule, canonical, title and description', function (): void {
    $seo = lynx()->seo(lynx()->pages->get('/courses/'), [
        'robots' => 'noindex, follow',
        'canonical' => '/courses/',
        'title' => 'Courses starting with A',
        'description' => '<p>Every course whose name starts with A.</p>',
    ]);

    expect($seo->robots)->toBe('noindex, follow')
        ->and($seo->canonical)->toBe('http://localhost/courses/')
        ->and($seo->title)->toBe('Courses starting with A')
        ->and($seo->description)->toBe('Every course whose name starts with A.');

    $webPage = collect($seo->jsonLd['@graph'])->firstWhere('@type', 'WebPage');

    expect($webPage['name'])->toBe('Courses starting with A');
});

it('can force a page to be indexed, and leaves other values alone', function (): void {
    config(['lynx-seo.noindex_hidden' => true]);
    $page = lynx()->pages->get('/courses/alpha/');

    expect(lynx()->seo($page, ['robots' => ''])->robots)->toBeNull()
        ->and(lynx()->seo($page, ['robots' => 'noindex'])->title)->toBe(lynx()->seo($page)->title);
});

it('refuses unknown overrides', function (): void {
    lynx()->seo(lynx()->pages->get('/courses/'), ['colour' => 'red']);
})->throws(InvalidArgumentException::class, 'colour');

it('takes the overrides as component props and directive arguments', function (): void {
    $page = lynx()->pages->get('/courses/');
    $component = Blade::render('<x-lynx-seo :page="$page" robots="noindex, follow" canonical="/courses/" />', ['page' => $page]);
    $directive = Blade::render("@seo(\$page, ['robots' => 'noindex, follow', 'canonical' => '/courses/'])", ['page' => $page]);

    expect($component)->toContain('<meta name="robots" content="noindex, follow">')
        ->toContain('<link rel="canonical" href="http://localhost/courses/">')
        ->and($directive)->toBe($component);
});
