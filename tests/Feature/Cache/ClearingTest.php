<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LynxCms\Cache\PageCacheEntry;
use LynxCms\Cache\Pages\CacheAddress;
use LynxCms\Cache\Pages\PageStore;
use LynxCms\Contracts\Actions\ActionHalted;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Runner\ActionResult;

beforeEach(function (): void {
    config([
        'lynx-cache.pages.enabled' => true,
        'lynx-cache.pages.path' => testingPath('lynx-cache'),
    ]);
    File::deleteDirectory(testingPath('lynx-cache'));
    installLynx();
    lynx()->templates->add('note', fields: [['name' => 'title', 'required' => true]]);
    notePage('a', '/');
    notePage('child', '/a/');
    notePage('sibling', '/a/');
    notePage('b', '/');
});

function notePage(string $name, string $parent): void
{
    $page = lynx()->pages->new('note', $parent);
    $page->name = $name;
    $page->title = 'Note '.$name;
    $page->save();
}

/** Visit the pages so they are stored, and return a check for whether each one still is. */
function visitAll(): Closure
{
    foreach (['/', '/a/', '/a/child/', '/a/sibling/', '/b/'] as $url) {
        test()->get($url)->assertOk();
    }

    return fn (string $path): bool => app(PageStore::class)->get(new CacheAddress('localhost', $path)) !== null;
}

function saveNote(string $url): void
{
    $page = lynx()->pages->get($url);
    $page->title = 'Changed';
    $page->save();
}

it('clears every page when a page changes and its template has no narrower rule', function (): void {
    $isStored = visitAll();
    expect($isStored('b'))->toBeTrue();

    saveNote('/a/child/');

    expect($isStored('b'))->toBeFalse()
        ->and($isStored(''))->toBeFalse()
        ->and(PageCacheEntry::query()->count())->toBe(0);
});

it('clears only what the template rule names', function (): void {
    config(['lynx-cache.pages.templates' => ['*' => [], 'note' => ['clear' => ['self', 'parents']]]]);
    $isStored = visitAll();

    saveNote('/a/child/');

    expect($isStored('a/child'))->toBeFalse()
        ->and($isStored('a'))->toBeFalse()
        ->and($isStored(''))->toBeFalse()
        ->and($isStored('a/sibling'))->toBeTrue()
        ->and($isStored('b'))->toBeTrue();
});

it('clears children, siblings and the home page when asked', function (): void {
    config(['lynx-cache.pages.templates' => ['*' => [], 'note' => ['clear' => ['children', 'siblings', 'home']]]]);
    $isStored = visitAll();

    saveNote('/a/');

    expect($isStored('a/child'))->toBeFalse()
        ->and($isStored('a/sibling'))->toBeFalse()
        ->and($isStored('b'))->toBeFalse()
        ->and($isStored(''))->toBeFalse()
        ->and($isStored('a'))->toBeTrue();
});

it('clears a deleted page by what the index knows of it', function (): void {
    config(['lynx-cache.pages.templates' => ['*' => [], 'note' => ['clear' => ['self']]]]);
    $isStored = visitAll();

    lynx()->pages->delete('/b/');

    expect($isStored('b'))->toBeFalse()->and($isStored('a'))->toBeTrue();
});

it('clears every page when a field or template changes', function (): void {
    $isStored = visitAll();

    lynx()->fields->add('subtitle', 'text');

    expect($isStored('a'))->toBeFalse();
});

it('keeps pages when an action rolls back or only dry runs', function (): void {
    $isStored = visitAll();

    lynx()->run(CreateField::class, ['name' => 'dry', 'type' => 'text'], dryRun: true);
    lynx()->hook(CreateField::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn (): ActionResult => lynx()->run(CreateField::class, ['name' => 'halted', 'type' => 'text']))->toThrow(ActionHalted::class);

    expect($isStored('a'))->toBeTrue();
});
