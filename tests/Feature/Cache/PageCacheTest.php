<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Cache\Pages\CacheAddress;
use LynxCms\Cache\Pages\Generation;
use LynxCms\Cache\Pages\PageStore;

beforeEach(function (): void {
    View::addLocation(__DIR__.'/../../Fixtures/views');
    config([
        'lynx-cache.pages.enabled' => true,
        'lynx-cache.pages.path' => testingPath('lynx-cache'),
        'lynx-cache.pages.debug' => true,
    ]);
    File::deleteDirectory(testingPath('lynx-cache'));
    notesSite();
});

/** Notes under the home page, shown by the core's fallback view, and a contact page with a form. */
function notesSite(): void
{
    installLynx();
    lynx()->templates->add('note', fields: [['name' => 'title', 'required' => true]]);
    lynx()->templates->add('note-form', fields: [['name' => 'title', 'required' => true]]);

    foreach (['a' => 'note', 'b' => 'note', 'contact' => 'note-form'] as $name => $template) {
        $page = lynx()->pages->new($template, '/');
        $page->name = $name;
        $page->title = 'Note '.$name;
        $page->save();
    }
}

function storedPage(string $path, string $query = ''): ?string
{
    return app(PageStore::class)->get(new CacheAddress('localhost', $path, $query))?->html;
}

it('stores a guest page and serves it again before the session starts', function (): void {
    $this->get('/a/')->assertOk()->assertHeader('X-Lynx-Cache', 'MISS');

    expect(storedPage('a'))->toContain('Note a')
        ->and(PageCacheEntry::query()->where('path', 'a')->value('template'))->toBe('note');

    $this->get('/a/')->assertOk()->assertSee('Note a')->assertHeader('X-Lynx-Cache', 'HIT')->assertCookieMissing('XSRF-TOKEN');
});

it('serves stored pages from the shared store driver too', function (): void {
    config(['lynx-cache.pages.driver' => 'store']);

    $this->get('/a/');

    $this->get('/a/')->assertHeader('X-Lynx-Cache', 'HIT');
});

it('stores nothing while the page cache is off', function (): void {
    config(['lynx-cache.pages.enabled' => false]);

    $this->get('/a/')->assertOk()->assertHeaderMissing('X-Lynx-Cache');

    expect(storedPage('a'))->toBeNull();
});

it('does not store pages for signed in users', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]))->get('/a/')->assertHeader('X-Lynx-Cache', 'SKIP; reason=signed-in');

    expect(storedPage('a'))->toBeNull();
});

it('does not store a page holding the CSRF token when refreshing is off', function (): void {
    config(['lynx-cache.pages.csrf' => 'skip']);

    $this->get('/contact/')->assertOk()->assertHeader('X-Lynx-Cache', 'SKIP; reason=csrf-token');

    expect(storedPage('contact'))->toBeNull();
});

it('does not store responses marked private after the middleware ran', function (): void {
    Event::listen(RequestHandled::class, fn (RequestHandled $event) => $event->response->headers->set('Cache-Control', 'no-store, private'));

    $this->get('/a/');

    expect(storedPage('a'))->toBeNull();
});

it('stores only the canonical address of a page', function (): void {
    $this->get('/A')->assertOk();

    expect(storedPage('a'))->toBeNull();
});

it('stores no errors or redirects', function (): void {
    $this->get('/missing/')->assertNotFound();
    $page = lynx()->pages->get('/a/');
    $page->name = 'renamed';
    $page->save();
    $this->get('/a/')->assertRedirect();

    expect(storedPage('missing'))->toBeNull()->and(storedPage('a'))->toBeNull();
});

it('does not store pages of templates left out', function (): void {
    config(['lynx-cache.pages.templates' => ['note-form' => []]]);

    $this->get('/a/')->assertHeader('X-Lynx-Cache', 'SKIP; reason=template');

    expect(storedPage('a'))->toBeNull();
});

it('lets requests with a bypass cookie through to Laravel', function (): void {
    $this->get('/a/');

    $this->withUnencryptedCookie('lynx_bypass', '1')->get('/a/')->assertHeader('X-Lynx-Cache', 'SKIP; reason=request');
});

it('does not store a page rendered while the cache was cleared', function (): void {
    Event::listen(RequestHandled::class, fn () => app(Generation::class)->bump());

    $this->get('/a/');

    expect(storedPage('a'))->toBeNull();
});

it('stores query variants only for allowed parameters, and drops tracking parameters', function (): void {
    config(['lynx-cache.pages.templates' => ['note' => ['parameters' => ['page']]]]);

    $this->get('/a/?page=2&utm_source=mail')->assertOk();
    $this->get('/a/?sort=title')->assertHeader('X-Lynx-Cache', 'SKIP; reason=query');
    $this->get('/a/?utm_campaign=x')->assertOk();

    expect(storedPage('a', 'page=2'))->not->toBeNull()
        ->and(storedPage('a', 'sort=title'))->toBeNull()
        ->and(storedPage('a'))->not->toBeNull();
});

it('stops storing variants of a page past the limit', function (): void {
    config(['lynx-cache.pages.templates' => ['note' => ['parameters' => ['page']]], 'lynx-cache.pages.max_variants' => 2]);

    foreach ([1, 2, 3] as $number) {
        $this->get("/a/?page={$number}");
    }

    expect(storedPage('a', 'page=2'))->not->toBeNull()->and(storedPage('a', 'page=3'))->toBeNull();
});

it('expires pages after the template lifetime', function (): void {
    config(['lynx-cache.pages.templates' => ['note' => ['lifetime' => 60]]]);

    $this->get('/a/');

    expect(PageCacheEntry::query()->where('path', 'a')->value('expires_at'))->not->toBeNull();
    $this->travel(61)->seconds();
    expect(storedPage('a'))->toBeNull();
});

it('lets an edge cache keep served pages when an edge TTL is set', function (): void {
    config(['lynx-cache.pages.edge_ttl' => 30]);
    $this->get('/a/');

    $this->get('/a/')->assertHeader('Cache-Control', 'max-age=0, public, s-maxage=30');
});

it('gives signed in users the bypass cookie on any page, so web servers stop sending them stored pages', function (): void {
    Route::middleware('web')->get('/cache-test/plain', fn (): string => 'plain');

    $this->actingAs(new GenericUser(['id' => 1]))->get('/cache-test/plain')->assertCookie('lynx_bypass', '1', encrypted: false);
});

it('takes the bypass cookie back from signed out visitors', function (): void {
    $this->withUnencryptedCookie('lynx_bypass', '1')->get('/a/')->assertCookieExpired('lynx_bypass');
});

it('treats the cookie session driver\'s data cookie as the session\'s own', function (): void {
    config(['session.driver' => 'cookie']);

    $this->get('/a/')->assertHeader('X-Lynx-Cache', 'MISS');

    expect(storedPage('a'))->not->toBeNull();
});
