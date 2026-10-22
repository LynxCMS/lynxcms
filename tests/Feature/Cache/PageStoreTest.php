<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LynxCms\Cache\Pages\CacheAddress;
use LynxCms\Cache\Pages\CachedPage;
use LynxCms\Cache\Pages\CachePageStore;
use LynxCms\Cache\Pages\FilePageStore;
use LynxCms\Cache\Pages\Generation;
use LynxCms\Cache\Pages\PageStore;

beforeEach(function (): void {
    config(['lynx-cache.pages.path' => testingPath('lynx-cache')]);
    File::deleteDirectory(testingPath('lynx-cache'));
});

dataset('drivers', [
    'files' => fn (): PageStore => app(FilePageStore::class),
    'store' => fn (): PageStore => app(CachePageStore::class),
]);

it('keeps a page and gives it back', function (PageStore $store): void {
    $address = new CacheAddress('localhost', 'courses/risk');
    $store->put($address, new CachedPage('<p>Risk</p>', createdAt: 100, expiresAt: null));

    $page = $store->get($address);

    expect($page?->html)->toBe('<p>Risk</p>')
        ->and($page?->createdAt)->toBe(100)
        ->and($page?->expiresAt)->toBeNull()
        ->and($store->get(new CacheAddress('localhost', 'courses/other')))->toBeNull();
})->with('drivers');

it('treats an expired page as missing', function (PageStore $store): void {
    $address = new CacheAddress('localhost', 'news', 'page=2');
    $store->put($address, new CachedPage('<p>Old</p>', createdAt: time() - 20, expiresAt: time() - 10));

    expect($store->get($address))->toBeNull();
})->with('drivers');

it('forgets one page and flushes them all', function (PageStore $store): void {
    $first = new CacheAddress('localhost', '');
    $second = new CacheAddress('localhost', 'about');
    $store->put($first, new CachedPage('home', 1, null));
    $store->put($second, new CachedPage('about', 1, null));

    $store->forget($first);
    expect($store->get($first))->toBeNull()->and($store->get($second))->not->toBeNull();

    $store->flush();
    expect($store->get($second))->toBeNull();
})->with('drivers');

it('writes plain HTML files a web server can send as they are', function (): void {
    app(FilePageStore::class)->put(new CacheAddress('localhost', 'about'), new CachedPage('<html><body>About</body></html>', 100, 200));

    $html = File::get(testingPath('lynx-cache').'/localhost/about/index.html');

    expect($html)->toStartWith('<html><body>About</body></html>')
        ->and($html)->toEndWith("<!-- lynx-cache 100 200 -->\n");
});

it('moves the generation on when everything is flushed', function (PageStore $store): void {
    $generation = app(Generation::class)->current();

    $store->flush();

    expect(app(Generation::class)->current())->not->toBe($generation);
})->with('drivers');
