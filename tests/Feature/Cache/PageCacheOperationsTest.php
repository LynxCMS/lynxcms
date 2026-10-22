<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use LynxCms\Cache\Actions\ClearPageCache;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Cache\Pages\CacheAddress;
use LynxCms\Cache\Pages\PageStore;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Runner\ActionResult;

beforeEach(function (): void {
    config([
        'lynx-cache.pages.enabled' => true,
        'lynx-cache.pages.path' => testingPath('lynx-cache'),
    ]);
    File::deleteDirectory(testingPath('lynx-cache'));
    installLynx();
    lynx()->templates->add('note', fields: [['name' => 'title', 'required' => true]]);
    lynx()->templates->add('memo', fields: [['name' => 'title', 'required' => true]]);

    foreach (['a' => 'note', 'b' => 'note', 'm' => 'memo'] as $name => $template) {
        $page = lynx()->pages->new($template, '/');
        $page->name = $name;
        $page->title = $name;
        $page->save();
    }

    // Saving a page clears every stored page, so visit them once all exist.
    foreach (['a', 'b', 'm'] as $name) {
        $this->get("/{$name}/")->assertOk();
    }
});

function isCached(string $path): bool
{
    return app(PageStore::class)->get(new CacheAddress('localhost', $path)) !== null;
}

it('clears every page, the pages given, or the pages of a template', function (): void {
    lynx()->run(ClearPageCache::class, ['pages' => [lynx()->pages->get('/a/')?->id]]);
    expect(isCached('a'))->toBeFalse()->and(isCached('b'))->toBeTrue();

    lynx()->run(ClearPageCache::class, ['template' => 'note']);
    expect(isCached('b'))->toBeFalse()->and(isCached('m'))->toBeTrue();

    lynx()->run(ClearPageCache::class);
    expect(isCached('m'))->toBeFalse()->and(PageCacheEntry::query()->count())->toBe(0);
});

it('lists what it would clear on a dry run and clears nothing', function (): void {
    $result = lynx()->run(ClearPageCache::class, ['template' => 'note'], dryRun: true);

    expect($result->changes->all())->toHaveCount(1)
        ->and($result->changes->all()[0]->summary)->toContain('2 stored pages')
        ->and(isCached('a'))->toBeTrue();
});

it('refuses pages and a template together, and unknown templates', function (array $input): void {
    expect(fn (): ActionResult => lynx()->run(ClearPageCache::class, $input))->toThrow(ValidationException::class);
})->with([
    'both' => [['pages' => [1], 'template' => 'note']],
    'unknown template' => [['template' => 'nope']],
]);

it('clears nothing when a hook halts the action', function (): void {
    lynx()->hook(ClearPageCache::class)->before(fn () => throw new ActionHalted('No.'));

    expect(fn (): ActionResult => lynx()->run(ClearPageCache::class))->toThrow(ActionHalted::class)
        ->and(isCached('a'))->toBeTrue();
});

it('prunes expired pages and shows what is stored', function (): void {
    PageCacheEntry::query()->where('path', 'a')->update(['expires_at' => now()->subMinute()]);

    $this->artisan('lynx:page-cache', ['--prune' => true, '--stats' => true])
        ->expectsOutputToContain('Pruned 1 expired page.')
        ->expectsOutputToContain('2 stored pages')
        ->assertSuccessful();

    expect(File::exists(testingPath('lynx-cache').'/localhost/a/index.html'))->toBeFalse()
        ->and(isCached('b'))->toBeTrue();
});

it('clears from the command line', function (): void {
    $this->artisan('lynx:page-cache', ['--clear' => true])->expectsOutputToContain('Cleared.')->assertSuccessful();

    expect(isCached('a'))->toBeFalse();
});

it('compiles the settings the pre-boot server reads', function (): void {
    $file = testingPath('lynx-cache').'.php';
    config(['lynx-cache.pages.compiled' => $file]);

    $this->artisan('lynx:page-cache', ['--compile' => true])->assertSuccessful();

    $compiled = require $file;
    expect($compiled['enabled'])->toBeTrue()
        ->and($compiled['hosts'])->toBe(['localhost'])
        ->and($compiled['path'])->toBe(testingPath('lynx-cache'));
});

it('prints web server rules', function (string $server, string $expected): void {
    $this->artisan('lynx:page-cache:server', ['server' => $server])->expectsOutputToContain($expected)->assertSuccessful();
})->with([
    ['nginx', 'try_files $lynx_cache'],
    ['apache', 'RewriteCond %{QUERY_STRING} ^$'],
    ['apache', 'remove Laravel\'s "Redirect Trailing Slashes" rule'],
    ['caddy', '@lynx_cache'],
    ['nginx', 'set $lynx_cache_control "no-cache, private";'],
    ['nginx', 'add_header Cache-Control $lynx_cache_control;'],
    ['apache', 'RewriteCond $1::%{REQUEST_FILENAME} ^(.*)::(.*?)\\1$'],
    ['apache', 'RewriteCond %2lynx-cache/%{HTTP_HOST}/$1/index.html -f'],
    ['apache', 'RewriteRule ^(.*)$ lynx-cache/%{HTTP_HOST}/$1/index.html [L,E=LYNX_CACHE:1]'],
    ['apache', 'Header set Cache-Control "no-cache, private" env=REDIRECT_LYNX_CACHE'],
    ['caddy', 'header @lynx_cache Cache-Control "no-cache, private"'],
]);

it('prints the edge cache header in web server rules when an edge TTL is set', function (string $server): void {
    config()->set('lynx-cache.pages.edge_ttl', 30);

    $this->artisan('lynx:page-cache:server', ['server' => $server])->expectsOutputToContain('public, max-age=0, s-maxage=30')->assertSuccessful();
})->with(['nginx', 'apache', 'caddy']);
