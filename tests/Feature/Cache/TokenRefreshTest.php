<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use LynxCms\Cache\Actions\SavePageCacheRules;
use LynxCms\Cache\Pages\CacheAddress;
use LynxCms\Cache\Pages\PageStore;
use LynxCms\Cache\Pages\TemplateRules;
use LynxCms\Cache\Pages\TokenRefresh;

beforeEach(function (): void {
    View::addLocation(__DIR__.'/../../Fixtures/views');
    config([
        'lynx-cache.pages.enabled' => true,
        'lynx-cache.pages.path' => testingPath('lynx-cache'),
        'lynx-cache.pages.debug' => true,
    ]);
    File::deleteDirectory(testingPath('lynx-cache'));
    installLynx();
    lynx()->templates->add('note-form', fields: [['name' => 'title', 'required' => true]]);
    $page = lynx()->pages->new('note-form', '/');
    $page->name = 'contact';
    $page->title = 'Contact';
    $page->save();
});

function storedContact(): ?string
{
    return app(PageStore::class)->get(new CacheAddress('localhost', 'contact'))?->html;
}

it('refreshes the token by default', function (): void {
    $this->get('/contact/')->assertHeader('X-Lynx-Cache', 'MISS');

    expect(storedContact())->toContain(TokenRefresh::PLACEHOLDER);
});

it('skips pages holding the token when refreshing is off', function (): void {
    config(['lynx-cache.pages.csrf' => 'skip']);

    $this->get('/contact/')->assertHeader('X-Lynx-Cache', 'SKIP; reason=csrf-token');

    expect(storedContact())->toBeNull();
});

it('stores a page with a placeholder for the token and the script that refreshes it', function (): void {
    config(['lynx-cache.pages.csrf' => 'refresh']);

    $response = $this->get('/contact/')->assertHeader('X-Lynx-Cache', 'MISS');
    $token = session()->token();
    $html = (string) storedContact();

    expect($response->getContent())->toContain($token)
        ->and($html)->not->toContain($token)
        ->and($html)->toContain(TokenRefresh::PLACEHOLDER)
        ->and($html)->toContain('<script src="'.route('lynx-cache.refresh-token.js').'" data-endpoint="'.route('lynx-cache.dynamic').'" defer></script></body>');
});

it('lets a template rule turn refreshing on or off', function (): void {
    config(['lynx-cache.pages.csrf' => 'skip', 'lynx-cache.pages.templates' => ['*' => [], 'note-form' => ['csrf' => 'refresh']]]);

    $this->get('/contact/');

    expect(storedContact())->not->toBeNull()
        ->and(app(TemplateRules::class)->for('note-form')?->refreshesToken)->toBeTrue();

    config(['lynx-cache.pages.csrf' => 'refresh', 'lynx-cache.pages.templates' => ['*' => ['csrf' => 'skip']]]);
    expect(app(TemplateRules::class)->for('note-form')?->refreshesToken)->toBeFalse();
});

it('hands out a fresh token, and the XSRF cookie, never cached', function (): void {
    $this->getJson(route('lynx-cache.dynamic', ['url' => url('/contact/')]))
        ->assertOk()
        ->assertJson(['token' => session()->token()])
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertCookie('XSRF-TOKEN');
});

it('adds the refresh script once when the page already has it', function (): void {
    $html = '<html><head><script src="'.route('lynx-cache.refresh-token.js').'" data-endpoint="x" defer></script></head><body>Vx81hTokenAbc</body></html>';

    $prepared = app(TokenRefresh::class)->prepare($html, 'Vx81hTokenAbc');

    expect(substr_count($prepared, 'refresh-token.js'))->toBe(1)
        ->and($prepared)->toContain('<body>'.TokenRefresh::PLACEHOLDER.'</body>');
});

it('prints the token and the refresh script with @lynxCsrf, and stores such pages with a placeholder', function (): void {
    config(['lynx-cache.pages.csrf' => 'refresh']);
    lynx()->templates->add('csrf-directive', fields: [['name' => 'title', 'required' => true]]);
    $page = lynx()->pages->new('csrf-directive', '/');
    $page->name = 'directive';
    $page->title = 'Directive';
    $page->save();

    $this->get('/directive/')->assertOk()
        ->assertSee('<meta name="csrf-token" content="'.session()->token().'">', false)
        ->assertSee('refresh-token.js', false);

    $html = (string) app(PageStore::class)->get(new CacheAddress('localhost', 'directive'))?->html;
    expect($html)->toContain('<meta name="csrf-token" content="'.TokenRefresh::PLACEHOLDER.'">')
        ->and(substr_count($html, 'refresh-token.js'))->toBe(1);
});

it('stores the assets Livewire injects after the middleware ran', function (): void {
    config(['lynx-cache.pages.csrf' => 'refresh']);
    Livewire::component('cache-probe', new class extends Component
    {
        public function render(): string
        {
            return '<div>probe</div>';
        }
    });
    lynx()->templates->add('live-probe', fields: [['name' => 'title', 'required' => true]]);
    $page = lynx()->pages->new('live-probe', '/');
    $page->name = 'live';
    $page->title = 'Live';
    $page->save();

    $this->get('/live/')->assertOk();

    $html = (string) app(PageStore::class)->get(new CacheAddress('localhost', 'live'))?->html;
    expect($html)->toContain('data-update-uri=')
        ->and($html)->toContain('data-csrf="'.TokenRefresh::PLACEHOLDER.'"');
});

it('serves the refresh script', function (): void {
    $response = $this->get(route('lynx-cache.refresh-token.js'))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('javascript')
        ->and($response->getContent())->toContain(TokenRefresh::PLACEHOLDER)->toContain('lynxCsrfToken')->toContain('lynx:cache-refreshed')
        // The session race guards: the cookie's token instead of a copied one, and plain forms.
        ->and($response->getContent())->toContain("'X-XSRF-TOKEN'")->toContain('withoutToken')->toContain('guardForms');
});

it('saves the token setting per template', function (): void {
    lynx()->run(SavePageCacheRules::class, ['templates' => ['note-form' => ['cache' => true, 'csrf' => 'refresh']]]);

    expect(app(TemplateRules::class)->for('note-form')?->refreshesToken)->toBeTrue();
});

it('ignores the no-store Livewire sends to keep components out of the back button cache', function (): void {
    config(['lynx-cache.pages.csrf' => 'refresh']);
    Event::listen(RequestHandled::class, fn (RequestHandled $event) => $event->response->headers->add([
        'Pragma' => 'no-cache',
        'Expires' => 'Fri, 01 Jan 1990 00:00:00 GMT',
        'Cache-Control' => 'no-cache, must-revalidate, no-store, max-age=0, private',
    ]));

    $this->get('/contact/');

    expect(storedContact())->not->toBeNull();
});

it('still treats a page marked private after Livewire as private', function (): void {
    config(['lynx-cache.pages.csrf' => 'refresh']);
    Event::listen(RequestHandled::class, function (RequestHandled $event): void {
        $event->response->headers->add(['Pragma' => 'no-cache', 'Expires' => 'Fri, 01 Jan 1990 00:00:00 GMT']);
        $event->response->headers->set('Cache-Control', 'no-store, private');
    });

    $this->get('/contact/');

    expect(storedContact())->toBeNull();
});
