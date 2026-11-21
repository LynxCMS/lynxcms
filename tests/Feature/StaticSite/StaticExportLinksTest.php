<?php

declare(strict_types=1);

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

beforeEach(function (): void {
    $this->dist = testingPath('dist');
    $this->public = testingPath('public');
    $this->views = testingPath('views');
    File::deleteDirectory($this->dist);
    File::deleteDirectory($this->public);
    File::ensureDirectoryExists($this->public.'/build/assets');
    File::ensureDirectoryExists($this->views.'/templates');
    app()->usePublicPath($this->public);
    View::addLocation($this->views);
    config(['lynx-static.path' => $this->dist, 'lynx-static.url' => 'https://static.example']);
    installLynx();
});

afterEach(function (): void {
    File::deleteDirectory($this->dist);
    File::deleteDirectory($this->public);
    File::deleteDirectory($this->views);
});

function staticLinksPage(string $template, string $view): string
{
    lynx()->templates->add($template, fields: [['name' => 'title', 'required' => true]]);
    File::put(testingPath('views')."/templates/{$template}.blade.php", $view);
    $page = lynx()->pages->new($template, '/');
    $page->name = 'page';
    $page->title = 'Page';
    $page->save();

    test()->artisan('lynx:static')->assertSuccessful();

    return File::get(testingPath('dist').'/page/index.html');
}

it('links to the site from the root, so one export works on any host, local ones too', function (): void {
    $html = staticLinksPage('links-page', <<<'BLADE'
        <html><head>
        <link rel="stylesheet" href="{{ asset('build/app.css') }}">
        <script src="{{ url('routed/app.js') }}" data-endpoint="{{ url('lynx-cache/dynamic') }}"></script>
        </head><body>
        <a href="{{ url('/') }}">Home</a> <a href="{{ url('a') }}?x=1">A</a>
        <img src="{{ asset('x.png') }}" srcset="{{ asset('x.png') }} 1x, {{ asset('x2.png') }} 2x" style="background:url({{ asset('bg.png') }})">
        <form action="{{ url('send') }}"></form>
        <a href="https://static.example.org/x">Other site</a> <a href="https://elsewhere.example/">Elsewhere</a>
        </body></html>
        BLADE);

    expect($html)->toContain('<link rel="stylesheet" href="/build/app.css">')
        ->toContain('<script src="/routed/app.js" data-endpoint="/lynx-cache/dynamic">')
        ->toContain('<a href="/">Home</a> <a href="/a?x=1">A</a>')
        ->toContain('<img src="/x.png" srcset="/x.png 1x, /x2.png 2x" style="background:url(/bg.png)">')
        ->toContain('<form action="/send">')
        ->toContain('href="https://static.example.org/x"')
        ->toContain('href="https://elsewhere.example/"');
});

it('keeps the full address where search engines and shared links need it', function (): void {
    $html = staticLinksPage('seo-page', <<<'BLADE'
        <html><head>
        <link rel="canonical" href="{{ url('page') }}">
        <link rel="alternate" hreflang="pt" href="{{ url('pt/page') }}">
        <meta property="og:url" content="{{ url('page') }}">
        <meta property="og:image" content="{{ asset('cover.jpg') }}">
        <script type="application/ld+json">{"url":"{{ url('page') }}"}</script>
        </head><body></body></html>
        BLADE);

    expect($html)->toContain('<link rel="canonical" href="https://static.example/page">')
        ->toContain('<link rel="alternate" hreflang="pt" href="https://static.example/pt/page">')
        ->toContain('<meta property="og:url" content="https://static.example/page">')
        ->toContain('<meta property="og:image" content="https://static.example/cover.jpg">')
        ->toContain('{"url":"https://static.example/page"}')
        ->and(File::get($this->dist.'/sitemap.xml'))->toContain('<loc>https://static.example/page</loc>');
});

it('keeps every link absolute when relative links are turned off', function (): void {
    config(['lynx-static.relative_links' => false]);

    $html = staticLinksPage('absolute-page', '<html><body><a href="{{ url(\'a\') }}">A</a></body></html>');

    expect($html)->toContain('<a href="https://static.example/a">A</a>');
});

it('loads the built assets, not the Vite dev server, while it is running', function (): void {
    File::put($this->public.'/hot', 'http://[::1]:5173');
    File::put($this->public.'/fonts-manifest.dev.json', '{"url":"http://[::1]:5173/x.woff2"}');
    File::put($this->public.'/build/manifest.json', json_encode(['resources/js/app.js' => ['file' => 'assets/app-1a2b.js', 'src' => 'resources/js/app.js', 'isEntry' => true]]));
    File::put($this->public.'/build/assets/app-1a2b.js', 'console.log(1)');

    $html = staticLinksPage('vite-page', '<html><head>@vite(\'resources/js/app.js\')</head><body></body></html>');

    expect($html)->toContain('src="/build/assets/app-1a2b.js"')
        ->not->toContain('5173')
        ->and(File::exists($this->dist.'/fonts-manifest.dev.json'))->toBeFalse()
        ->and(app(Vite::class)->hotFile())->toBe($this->public.'/hot');
});
