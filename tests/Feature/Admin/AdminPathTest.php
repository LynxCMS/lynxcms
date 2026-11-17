<?php

declare(strict_types=1);

use LynxCms\Admin\AdminPath;
use LynxCms\Admin\InvalidAdminPath;

/*
 * The admin's path (LYNX_ADMIN_PREFIX) is one or more URL segments of lowercase letters, digits,
 * "-" and "_". An empty path would put the admin's screens over the site's own URLs, so it is
 * refused with a message that says what to set instead.
 */

it('reads the configured path without its slashes', function (): void {
    config(['lynx-admin.prefix' => '/backstage/']);

    expect(AdminPath::current())->toBe('backstage');
});

it('accepts paths of several segments', function (): void {
    expect(AdminPath::validate('team/cms-2'))->toBe('team/cms-2');
});

it('refuses an empty path', function (): void {
    AdminPath::validate(' / ');
})->throws(InvalidAdminPath::class, 'LYNX_ADMIN_PREFIX');

it('refuses characters a URL segment cannot hold as is', function (string $path): void {
    expect(fn (): string => AdminPath::validate($path))->toThrow(InvalidAdminPath::class);
})->with(['back stage', 'Admin', 'a//b', '../admin', 'é-admin', 'admin?x=1']);

it('makes random paths that are valid and hard to guess', function (): void {
    $first = AdminPath::random();

    expect(AdminPath::validate($first))->toBe($first)
        ->and(strlen($first))->toBe(20)
        ->and(AdminPath::random())->not->toBe($first);
});
