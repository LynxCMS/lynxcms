<?php

declare(strict_types=1);

use LynxCms\Cache\Pages\CacheAddress;
use LynxCms\Cache\Pages\RequestRules;

function cacheRules(): RequestRules
{
    return new RequestRules(hosts: ['example.com'], bypassCookies: ['lynx_bypass'], ignoredParameters: ['utm_*', 'gclid']);
}

it('gives a guest GET request an address from its host and decoded, lowercased path', function (): void {
    $address = cacheRules()->addressFor('GET', 'Example.com', '/Cursos/Ação/', '', []);

    expect($address)->toBeInstanceOf(CacheAddress::class)
        ->and($address->host)->toBe('example.com')
        ->and($address->path)->toBe('cursos/ação')
        ->and($address->query)->toBe('')
        ->and($address->file())->toBe('example.com/cursos/ação/index.html');
});

it('stores the home page at the root of the host folder', function (): void {
    expect(cacheRules()->addressFor('HEAD', 'example.com', '/', '', [])?->file())->toBe('example.com/index.html');
});

it('refuses other methods, unknown hosts and bypass cookies', function (string $method, string $host, array $cookies): void {
    expect(cacheRules()->addressFor($method, $host, '/about/', '', $cookies))->toBeNull();
})->with([
    'POST' => ['POST', 'example.com', []],
    'unknown host' => ['GET', 'evil.test', []],
    'bypass cookie' => ['GET', 'example.com', ['lynx_bypass' => '1']],
]);

it('refuses paths that could leave the cache folder', function (string $path): void {
    expect(cacheRules()->addressFor('GET', 'example.com', $path, '', []))->toBeNull();
})->with(['/../secret/', '/a/%2e%2e/b/', '/a\\b/', "/a\0b/", '/a//b/']);

it('drops ignored parameters and sorts the rest', function (): void {
    $address = cacheRules()->addressFor('GET', 'example.com', '/news/', 'sort=date&utm_source=x&page=2&gclid=1', []);

    expect($address?->query)->toBe('page=2&sort=date')
        ->and($address?->file())->toBe('example.com/news/q-'.sha1('page=2&sort=date').'.html')
        ->and(cacheRules()->addressFor('GET', 'example.com', '/news/', 'utm_medium=y', [])?->query)->toBe('');
});

it('gives the same address to the same parameters in any order', function (): void {
    $first = cacheRules()->addressFor('GET', 'example.com', '/news/', 'b=2&a=1', []);
    $second = cacheRules()->addressFor('GET', 'example.com', '/news/', 'a=1&b=2', []);

    expect($first?->key())->toBe($second?->key());
});
