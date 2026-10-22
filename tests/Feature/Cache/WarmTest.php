<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Cache\Pages\CacheAddress;
use LynxCms\Cache\Pages\PageStore;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\PagePassword\Actions\EnablePagePassword;
use LynxCms\PagePassword\PagePasswordSchema;

beforeEach(function (): void {
    View::addLocation(__DIR__.'/../../Fixtures/views');
    config([
        'lynx-cache.pages.enabled' => true,
        'lynx-cache.pages.path' => testingPath('lynx-cache'),
    ]);
    File::deleteDirectory(testingPath('lynx-cache'));
    installLynx();
    lynx()->languages->add('pt', 'Português', fallback: 'default');
    lynx()->templates->add('note', fields: [['name' => 'title', 'required' => true]]);

    foreach (['a', 'b'] as $name) {
        $page = lynx()->pages->new('note', '/');
        $page->name = $name;
        $page->title = 'Note '.$name;
        $page->setLanguageName('pt', 'nota-'.$name);
        $page->save();
    }

    $hidden = lynx()->pages->new('note', '/');
    $hidden->name = 'hidden';
    $hidden->title = 'Hidden';
    $hidden->setStatus(PageStatus::HIDDEN);
    $hidden->save();
});

function isWarm(string $path): bool
{
    return app(PageStore::class)->get(new CacheAddress('localhost', $path)) !== null;
}

it('stores every public page in every language it is live in', function (): void {
    $this->artisan('lynx:page-cache', ['--warm' => true])
        ->expectsOutputToContain('Warmed 7 pages')
        ->assertSuccessful();

    expect(isWarm(''))->toBeTrue()
        ->and(isWarm('a'))->toBeTrue()
        ->and(isWarm('pt/nota-b'))->toBeTrue()
        ->and(isWarm('hidden'))->toBeTrue()
        ->and(app(PageStore::class)->get(new CacheAddress('localhost', 'pt/nota-a'))?->html)->toContain('lang="pt');
});

it('leaves out templates without a cache rule and says why pages were skipped', function (): void {
    config(['lynx-cache.pages.templates' => ['note' => []]]);

    $this->artisan('lynx:page-cache', ['--warm' => true])
        ->expectsOutputToContain('Warmed 5 pages')
        ->assertSuccessful();

    expect(isWarm(''))->toBeFalse();
});

it('reports pages that were not stored, with the reason', function (): void {
    lynx()->templates->add('note-form', fields: [['name' => 'title', 'required' => true]]);
    $form = lynx()->pages->new('note-form', '/');
    $form->name = 'contact';
    $form->title = 'Contact';
    $form->save();
    config(['lynx-cache.pages.csrf' => 'skip']);

    $this->artisan('lynx:page-cache', ['--warm' => true])
        ->expectsOutputToContain('Not stored, csrf-token:')
        ->assertSuccessful();
});

it('does nothing while the page cache is off', function (): void {
    config(['lynx-cache.pages.enabled' => false]);

    $this->artisan('lynx:page-cache', ['--warm' => true])->expectsOutputToContain('The page cache is off')->assertSuccessful();

    expect(PageCacheEntry::query()->count())->toBe(0);
});

it('can warm twice in one process', function (): void {
    $this->artisan('lynx:page-cache', ['--warm' => true])->assertSuccessful();
    $this->artisan('lynx:page-cache', ['--clear' => true, '--warm' => true])->expectsOutputToContain('Warmed 7 pages')->assertSuccessful();
});

it('does not carry one visit\'s cookies into the next, with the cookie session driver', function (): void {
    config(['session.driver' => 'cookie']);

    $this->artisan('lynx:page-cache', ['--warm' => true])
        ->expectsOutputToContain('Warmed 7 pages')
        ->doesntExpectOutputToContain('cookie:')
        ->assertSuccessful();
});

it('gives every warmed page Livewire\'s assets, not only the first', function (): void {
    Livewire::component('cache-probe', new class extends Component
    {
        public function render(): string
        {
            return '<div>probe</div>';
        }
    });
    lynx()->templates->add('live-probe', fields: [['name' => 'title', 'required' => true]]);

    foreach (['one', 'two'] as $name) {
        $page = lynx()->pages->new('live-probe', '/');
        $page->name = $name;
        $page->title = $name;
        $page->save();
    }

    $this->artisan('lynx:page-cache', ['--warm' => true])->assertSuccessful();

    foreach (['one', 'two'] as $path) {
        expect(app(PageStore::class)->get(new CacheAddress('localhost', $path))?->html)->toContain('data-update-uri=');
    }
});

it('warms pages as a visitor sees them, without what a visitor may not see', function (): void {
    lynx()->run(EnablePagePassword::class, ['template' => 'note']);
    $locked = lynx()->pages->get('/b');
    $locked->{PagePasswordSchema::FIELD} = 'secret';
    $locked->save();
    lynx()->templates->add('note-list', fields: [['name' => 'title', 'required' => true]]);
    $list = lynx()->pages->new('note-list', '/');
    $list->name = 'list';
    $list->title = 'List';
    $list->save();

    // The command runs in a console process, where visibility rules are off unless the warmer turns them on.
    app()->instance('env', 'production');

    try {
        $this->artisan('lynx:page-cache', ['--warm' => true])->assertSuccessful();
    } finally {
        app()->instance('env', 'testing');
    }

    $html = app(PageStore::class)->get(new CacheAddress('localhost', 'list'))?->html;

    expect($html)->toContain('Note a')
        ->and($html)->not->toContain('Note b')
        ->and(isWarm('b'))->toBeFalse();
});
