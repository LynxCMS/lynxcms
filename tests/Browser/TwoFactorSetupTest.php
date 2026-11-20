<?php

declare(strict_types=1);

use Workbench\App\Models\User;

/*
 * The first sign-in when the admin requires two-factor authentication (ADR-0128): the user is
 * sent to account security, scans the QR code, types the code and keeps the recovery codes.
 * Only then do the other screens open.
 */

beforeEach(function (): void {
    signInSite();
    config(['lynx-admin.auth.require_two_factor' => true]);
    $this->ana = browserUser('ana@example.com');
});

it('sets up two-factor authentication on the first sign-in, then opens the admin', function (): void {
    $page = visit('/lynx/login')
        ->fill('email', 'ana@example.com')
        ->fill('password', BROWSER_PASSWORD)
        ->click('button[type=submit]')
        ->assertSee('Two-factor authentication is required for the admin.')
        ->press('Turn on')
        ->assertSee('Scan the QR code with your authenticator app');

    $page->fill('#two-factor-code', currentCode($this->ana))
        ->press('Confirm')
        ->assertSee('Recovery codes')
        ->assertSee($this->ana->refresh()->recoveryCodes()[0])
        ->assertNoJavaScriptErrors();

    expect(User::query()->find($this->ana->id)?->two_factor_confirmed_at)->not->toBeNull();

    $page->navigate('/lynx/pages')->assertPathIs('/lynx/pages')->assertSee('Sign out');
});

it('keeps the other screens closed until two-factor authentication is set up', function (): void {
    visit('/lynx/login')
        ->fill('email', 'ana@example.com')
        ->fill('password', BROWSER_PASSWORD)
        ->click('button[type=submit]')
        ->navigate('/lynx/pages')
        ->assertSee('Two-factor authentication is required for the admin.');
});
