<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * lynx:admin-path shows where the admin is, and moves it: to a path the developer chooses, such as
 * /admin on a site that needs /lynx for a page, or to a random one that is hard to guess. It writes
 * LYNX_ADMIN_PREFIX to .env, as key:generate writes APP_KEY, and refuses a path a page already uses.
 */

beforeEach(function (): void {
    courseSite();
    $this->envPath = testingPath('admin-path-env');
    File::ensureDirectoryExists($this->envPath);
    File::put($this->envPath.'/.env', "APP_NAME=Lynx\nLYNX_ADMIN_PREFIX=lynx\nAPP_DEBUG=true\n");
    app()->useEnvironmentPath($this->envPath);
});

afterEach(function (): void {
    File::deleteDirectory($this->envPath);
});

function envFile(): string
{
    return File::get(test()->envPath.'/.env');
}

it('says where the admin is', function (): void {
    $this->artisan('lynx:admin-path')
        ->expectsOutputToContain('http://localhost/lynx')
        ->assertSuccessful();

    expect(envFile())->toContain('LYNX_ADMIN_PREFIX=lynx');
});

it('moves the admin to a chosen path', function (): void {
    $this->artisan('lynx:admin-path', ['path' => '/admin/'])
        ->expectsOutputToContain('http://localhost/admin')
        ->assertSuccessful();

    expect(envFile())->toBe("APP_NAME=Lynx\nLYNX_ADMIN_PREFIX=admin\nAPP_DEBUG=true\n");
});

it('adds the setting when .env does not have it yet', function (): void {
    File::put($this->envPath.'/.env', "APP_NAME=Lynx\n");

    $this->artisan('lynx:admin-path', ['path' => 'admin'])->assertSuccessful();

    expect(envFile())->toBe("APP_NAME=Lynx\nLYNX_ADMIN_PREFIX=admin\n");
});

it('moves the admin to a random path', function (): void {
    $this->artisan('lynx:admin-path', ['--random' => true])->assertSuccessful();

    expect(envFile())->toMatch('/^LYNX_ADMIN_PREFIX=[a-z0-9]{20}$/m')
        ->not->toContain('LYNX_ADMIN_PREFIX=lynx');
});

it('refuses a path that is not valid, and leaves .env alone', function (): void {
    $this->artisan('lynx:admin-path', ['path' => 'back office'])
        ->expectsOutputToContain('lowercase letters')
        ->assertFailed();

    expect(envFile())->toContain('LYNX_ADMIN_PREFIX=lynx');
});

it('refuses a path a page uses, naming the page', function (): void {
    $this->artisan('lynx:admin-path', ['path' => 'courses'])
        ->expectsOutputToContain('/courses')
        ->assertFailed();

    expect(envFile())->toContain('LYNX_ADMIN_PREFIX=lynx');
});

it('refuses a path that a page in another language uses below it', function (): void {
    $this->artisan('lynx:admin-path', ['path' => 'pt/cursos'])->assertFailed();
});

it('refuses a path and a random path given together', function (): void {
    $this->artisan('lynx:admin-path', ['path' => 'admin', '--random' => true])->assertFailed();
});
