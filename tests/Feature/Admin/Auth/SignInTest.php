<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use LynxCms\Admin\Auth\AdminAuth;
use Workbench\App\Models\User;

beforeEach(function (): void {
    courseSite();
    $this->user = User::query()->forceCreate(['name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => Hash::make('correct horse')]);
});

it('sends guests of the admin to its sign-in screen', function (): void {
    $this->get('/lynx/pages')->assertRedirect('/lynx/login');
    $this->get('/lynx/login')->assertOk()->assertSee('Sign in')->assertSee('Forgot your password?')->assertSee('admin.css');
});

it('signs in with email and password', function (): void {
    $this->post('/lynx/login', ['email' => 'ANA@example.com', 'password' => 'correct horse'])->assertRedirect('/lynx/pages');

    $this->assertAuthenticatedAs($this->user);
});

it('refuses a wrong password', function (): void {
    $this->from('/lynx/login')->post('/lynx/login', ['email' => 'ana@example.com', 'password' => 'wrong'])
        ->assertRedirect('/lynx/login')->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('has no registration', function (): void {
    $this->get('/lynx/register')->assertNotFound();

    expect(Route::has('register'))->toBeFalse()->and(Route::has('register.store'))->toBeFalse();
});

it('signs out from the sidebar', function (): void {
    $page = $this->actingAs($this->user)->get('/lynx/pages')->assertSee('Sign out')->assertSee('Ana Lima');

    expect($page->getContent())->toMatch('/<button\s+type="submit"\s+data-slot="sidebar-menu-button"/');

    $this->post('/lynx/logout')->assertRedirect('/lynx/login');
    $this->assertGuest();
});

it('resets a forgotten password', function (): void {
    Notification::fake();

    $this->get('/lynx/forgot-password')->assertOk()->assertSee('Email a reset link');
    $this->post('/lynx/forgot-password', ['email' => 'ana@example.com'])->assertSessionHas('status');

    $token = null;
    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->get("/lynx/reset-password/{$token}?email=ana@example.com")->assertOk()->assertSee('Choose a new password');
    $this->post('/lynx/reset-password', ['token' => $token, 'email' => 'ana@example.com', 'password' => 'a new long password', 'password_confirmation' => 'a new long password'])
        ->assertRedirect('/lynx/login');

    expect(Auth::attempt(['email' => 'ana@example.com', 'password' => 'a new long password']))->toBeTrue();
});

it('rejects a short new password', function (): void {
    $token = app('auth.password.broker')->createToken($this->user);

    $this->post('/lynx/reset-password', ['token' => $token, 'email' => 'ana@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
});

it('is on unless the app configures Fortify itself', function (): void {
    config(['lynx-admin.auth.enabled' => 'auto']);
    expect(AdminAuth::isEnabled())->toBeTrue();

    config(['lynx-admin.auth.enabled' => false]);
    expect(AdminAuth::isEnabled())->toBeFalse();
});

it('names who signs users in: Lynx, the app, or neither', function (): void {
    config(['lynx-admin.auth.enabled' => 'auto']);
    expect([AdminAuth::mode(), AdminAuth::managesAccounts()])->toBe(['lynx', true]);

    config(['lynx-admin.auth.enabled' => 'app']);
    expect([AdminAuth::mode(), AdminAuth::isEnabled(), AdminAuth::managesAccounts()])->toBe(['app', false, true]);

    config(['lynx-admin.auth.enabled' => false]);
    expect([AdminAuth::mode(), AdminAuth::managesAccounts()])->toBe(['off', false]);
});
