<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use LynxCms\Admin\Livewire\PageEditor;
use LynxCms\Cache\Actions\SavePageCacheRules;
use LynxCms\Cache\Admin\Livewire\PageCacheTab;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Pages\Page;

/*
 * The page editor's Cache tab, for people who manage the page cache: whether guests get a stored
 * copy of the page, why not when they don't, and how the page cache treats pages.
 */

beforeEach(function (): void {
    config([
        'lynx-cache.pages.enabled' => true,
        'lynx-cache.pages.path' => testingPath('lynx-cache'),
    ]);
    File::deleteDirectory(testingPath('lynx-cache'));
    installLynx();
    lynx()->templates->add('note', fields: [['name' => 'title', 'required' => true]]);
    lynx()->templates->add('snippet', fields: ['title'], hasUrl: false);
    $page = lynx()->pages->new('note', '/');
    $page->name = 'a';
    $page->title = 'A';
    $page->save();
    $this->actingAs(new GenericUser(['id' => 1]));
});

function cacheTabPage(): Page
{
    $page = lynx()->pages->get('/a/');
    assert($page instanceof Page);

    return $page;
}

it('adds a Cache tab to pages with a URL', function (): void {
    Livewire::test(PageEditor::class, ['pageId' => cacheTabPage()->id])
        ->assertSeeInOrder(['Content', 'Cache', 'Settings'])
        ->set('tab', 'cache')
        ->assertSeeLivewire(PageCacheTab::class);
});

it('leaves the tab out on pages without a URL', function (): void {
    $snippet = lynx()->pages->new('snippet', '/');
    $snippet->name = 'snippet';
    $snippet->title = 'Snippet';
    $snippet->save();

    Livewire::test(PageEditor::class, ['pageId' => $snippet->id])->assertDontSee('Cache');
});

it('shows other editors whether the page is cached, without instructions or clearing', function (): void {
    $this->app['auth']->forgetGuards();
    $this->get('/a')->assertOk();
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view'], ['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['writer']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    Livewire::test(PageEditor::class, ['pageId' => cacheTabPage()->id])->assertSee('Cache');
    Livewire::test(PageCacheTab::class, ['pageId' => cacheTabPage()->id])
        ->assertSee('Yes, this page is in the cache.')
        ->assertSeeHtml('>/a</td>')
        ->assertDontSee('Clear this page')
        ->assertDontSee('Page cache settings')
        ->assertDontSee('How the page cache works')
        ->assertDontSee('kept until its content changes')
        ->call('clear')
        ->assertForbidden();

    expect(PageCacheEntry::query()->where('page_id', cacheTabPage()->id)->count())->toBe(1);
});

it('keeps the fix out of the reason for other editors', function (): void {
    config(['lynx-cache.pages.enabled' => false]);
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['writer']]);
    $this->actingAs(new GenericUser(['id' => 2]));

    Livewire::test(PageCacheTab::class, ['pageId' => cacheTabPage()->id])
        ->assertSee('The page cache is off')
        ->assertDontSee('LYNX_PAGE_CACHE');
});

it('says a page is not stored yet until a guest visits it', function (): void {
    Livewire::test(PageCacheTab::class, ['pageId' => cacheTabPage()->id])
        ->assertSee('Not stored yet')
        ->assertSee('kept until its content changes')
        ->assertSee('Pages are stored for requests to');
});

it('lists the stored URLs once a guest has visited, and clears them', function (bool $trailingSlash, string $url): void {
    config(['lynx.trailing_slash' => $trailingSlash]);
    $this->app['auth']->forgetGuards();
    $this->get($url)->assertOk();
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageCacheTab::class, ['pageId' => cacheTabPage()->id])
        ->assertSee('Yes, this page is in the cache.')
        ->assertSee('when they visit this URL')
        // Written as the site writes its URLs (lynx.trailing_slash).
        ->assertSeeHtml(">{$url}</td>")
        ->call('clear')
        ->assertSee('Not stored yet');

    expect(PageCacheEntry::query()->where('page_id', cacheTabPage()->id)->count())->toBe(0);
})->with([
    'without a trailing slash' => [false, '/a'],
    'with a trailing slash' => [true, '/a/'],
]);

it('says why a page is not stored', function (Closure $setUp, string $reason): void {
    $setUp();

    Livewire::test(PageCacheTab::class, ['pageId' => cacheTabPage()->id])->assertSeeText($reason);
})->with([
    'cache off' => [fn () => config(['lynx-cache.pages.enabled' => false]), 'The page cache is off'],
    'template off' => [fn (): ActionResult => lynx()->run(SavePageCacheRules::class, ['templates' => ['note' => ['cache' => false]]]), 'Pages of the note template are not stored'],
    'unpublished' => [fn () => lynx()->pages->setStatus((int) cacheTabPage()->id, add: ['unpublished']), 'unpublished'],
]);

it('explains how the page cache treats pages', function (): void {
    Livewire::test(PageCacheTab::class, ['pageId' => cacheTabPage()->id])
        ->assertSee('How the page cache works')
        ->assertSee('Signed-in people always see the live page')
        ->assertSeeHtml(route('lynx.admin.page-cache', ['page' => cacheTabPage()->id]));
});
