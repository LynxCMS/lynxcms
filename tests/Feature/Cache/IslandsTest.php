<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use LynxCms\Cache\Islands\Island;
use LynxCms\Cache\Pages\CacheAddress;
use LynxCms\Cache\Pages\PageStore;

beforeEach(function (): void {
    View::addLocation(__DIR__.'/../../Fixtures/views');
    config([
        'lynx-cache.pages.enabled' => true,
        'lynx-cache.pages.path' => testingPath('lynx-cache'),
    ]);
    File::deleteDirectory(testingPath('lynx-cache'));
    installLynx();
    lynx()->languages->add('pt', 'Português', fallback: 'default');
    lynx()->templates->add('island-page', fields: [['name' => 'title', 'required' => true]]);
    $page = lynx()->pages->new('island-page', '/');
    $page->name = 'island';
    $page->title = 'Island';
    $page->setLanguageName('pt', 'ilha');
    $page->setLanguageValue('pt', 'title', 'Ilha');
    $page->save();
});

function storedIsland(string $path = 'island'): string
{
    return (string) app(PageStore::class)->get(new CacheAddress('localhost', $path))?->html;
}

/** The encrypted payloads a page's islands carry. */
function islandPayloads(string $html): array
{
    preg_match_all('/<lynx-island data-island="([^"]+)"/', $html, $matches);

    return $matches[1];
}

it('stores a placeholder for the island, with its fallback and the script that fills it', function (): void {
    $response = $this->get('/island/')->assertOk()->assertSee('Loading')->assertDontSee('Hello Ada');
    $html = storedIsland();

    expect(islandPayloads($html))->toHaveCount(1)
        ->and($html)->not->toContain('Hello Ada')
        ->and($html)->toContain(route('lynx-cache.refresh-token.js'))
        ->and(islandPayloads((string) $response->getContent()))->toHaveCount(1);
});

it('renders islands inline for signed in users', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]))->get('/island/')->assertSee('Hello Ada on Island in default', false)->assertDontSee('<lynx-island', false);
});

it('renders islands inline while the page cache is off', function (): void {
    config(['lynx-cache.pages.enabled' => false]);

    $this->get('/island/')->assertSee('Hello Ada on Island', false)->assertDontSee('<lynx-island', false);
});

it('renders islands for the visitor, in the page\'s language, with the token', function (): void {
    $this->get('/pt/ilha/');
    $payloads = islandPayloads(storedIsland('pt/ilha'));

    $this->getJson(route('lynx-cache.dynamic', ['url' => url('/pt/ilha/'), 'islands' => $payloads]))
        ->assertOk()
        ->assertJsonPath('islands.0', fn (string $html): bool => str_contains($html, 'Hello Ada on Ilha in pt'))
        ->assertJsonPath('token', session()->token());
});

it('ignores islands it cannot read', function (): void {
    $this->getJson(route('lynx-cache.dynamic', ['islands' => ['not-a-payload']]))
        ->assertOk()
        ->assertJsonPath('islands.0', null);
});

it('renders Livewire components as islands, and keeps Livewire\'s assets on the stored page', function (): void {
    Livewire::component('cache-probe', new class extends Component
    {
        public int $count = 0;

        public function render(): string
        {
            return '<div class="probe">probe {{ $count }}</div>';
        }
    });
    lynx()->templates->add('live-island', fields: [['name' => 'title', 'required' => true]]);
    $page = lynx()->pages->new('live-island', '/');
    $page->name = 'live';
    $page->title = 'Live';
    $page->save();

    $this->get('/live/');
    $html = storedIsland('live');

    expect($html)->toContain('data-update-uri=')->not->toContain('class="probe"');

    $this->getJson(route('lynx-cache.dynamic', ['islands' => islandPayloads($html)]))
        ->assertJsonPath('islands.0', fn (string $island): bool => str_contains($island, 'wire:snapshot') && str_contains($island, 'probe 0'));
});

it('leaves an island empty when its view is gone', function (): void {
    $payload = new Island(Island::VIEW, 'partials.missing', [], null, 'default')->encrypt();

    $this->getJson(route('lynx-cache.dynamic', ['islands' => [$payload]]))->assertOk()->assertJsonPath('islands.0', null);
});

it('sends the assets and scripts of Livewire components inside islands', function (): void {
    Livewire::component('island-assets', new class extends Component
    {
        public int $count = 0;

        public function render(): Illuminate\Contracts\View\View
        {
            return view('livewire.island-assets');
        }
    });
    lynx()->templates->add('assets-island', fields: [['name' => 'title', 'required' => true]]);
    $page = lynx()->pages->new('assets-island', '/');
    $page->name = 'charts';
    $page->title = 'Charts';
    $page->save();

    $this->get('/charts');
    $stored = storedIsland('charts');
    expect($stored)->not->toContain('island-chart.js');

    $response = $this->getJson(route('lynx-cache.dynamic', ['islands' => islandPayloads($stored)]))->assertOk();

    expect(implode('', (array) $response->json('assets')))->toContain('<script src="/js/island-chart.js"></script>')
        ->and((string) $response->json('islands.0'))->toContain('wire:effects')->toContain('islandScriptRan');
});

it('sends no assets for islands without any', function (): void {
    $this->get('/island');

    $this->getJson(route('lynx-cache.dynamic', ['islands' => islandPayloads(storedIsland())]))
        ->assertOk()
        ->assertJsonPath('assets', []);
});
