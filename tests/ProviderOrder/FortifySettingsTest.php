<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use LynxCms\Admin\Http\UseAdminLocale;
use Workbench\App\Models\User;

/*
 * Lynx's sign-in screens are Laravel Fortify and laravel/passkeys, configured by the admin at
 * register time (AdminAuth::configure()). Fortify copies some of that config into passkeys' own
 * while it registers, often before the admin has written it. These tests pin each setting the
 * admin relies on, so a Fortify or passkeys release that reads them differently fails here
 * instead of quietly changing the sign-in screens.
 */

beforeEach(function (): void {
    courseSite();
});

it('puts each sign-in route under the admin prefix, in the admin language, with Lynx\'s rate limit', function (string $name, string $limiter): void {
    $route = Route::getRoutes()->getByName($name);

    expect($route?->uri())->toStartWith('lynx/')
        ->and($route?->gatherMiddleware())->toContain(UseAdminLocale::class, "throttle:{$limiter}");
})->with([
    'password' => ['login.store', 'lynx-login'],
    'two-factor code' => ['two-factor.login.store', 'lynx-two-factor'],
    'passkey' => ['passkey.login', 'lynx-passkeys'],
    'passkey options' => ['passkey.login-options', 'lynx-passkeys'],
]);

it('asks for the password before a passkey is added', function (): void {
    expect(Route::getRoutes()->getByName('passkey.store')?->gatherMiddleware())->toContain('password.confirm');
});

it('opens the admin after a passkey sign-in', function (): void {
    expect(config('passkeys.redirect'))->toBe('/lynx/pages');
});

it('stops password guesses after five a minute for one account', function (): void {
    User::query()->forceCreate(['name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => Hash::make('correct horse')]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/lynx/login', ['email' => 'ana@example.com', 'password' => "wrong {$attempt}"])->assertRedirect();
    }

    $this->post('/lynx/login', ['email' => 'ANA@example.com', 'password' => 'wrong again'])->assertTooManyRequests();
});
