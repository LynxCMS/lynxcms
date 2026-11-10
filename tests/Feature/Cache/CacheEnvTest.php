<?php

declare(strict_types=1);

it('reads its on and off settings from .env as booleans, as .env files and phpunit.xml set them', function (string $variable, string $key, string $value, bool $expected): void {
    putenv($variable.'='.$value);

    try {
        $config = require dirname(__DIR__, 3).'/packages/cache/config/lynx-cache.php';
    } finally {
        putenv($variable);
    }

    expect(data_get($config, $key))->toBe($expected);
})->with(function (): iterable {
    $settings = [
        'LYNX_PAGE_CACHE' => 'pages.enabled',
        'LYNX_PAGE_CACHE_DEBUG' => 'pages.debug',
        'LYNX_FRAGMENT_CACHE' => 'fragments.enabled',
    ];

    foreach ($settings as $variable => $key) {
        foreach ([['1', true], ['true', true], ['0', false], ['false', false]] as [$value, $expected]) {
            yield "{$variable}={$value}" => [$variable, $key, $value, $expected];
        }
    }
});
