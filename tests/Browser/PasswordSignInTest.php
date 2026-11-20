<?php

declare(strict_types=1);

/*
 * Signing in to the admin with an email and a password, in a real browser (item 7 of the road
 * to 1.0). The HTTP tests cover the rules; these cover the screens people use.
 */

beforeEach(function (): void {
    signInSite();
    browserUser('ana@example.com');
});

it('signs in with the right password and opens the admin', function (): void {
    visit('/lynx/login')
        ->fill('email', 'ana@example.com')
        ->fill('password', BROWSER_PASSWORD)
        ->click('button[type=submit]')
        ->assertPathIsNot('/lynx/login')
        ->assertSee('Sign out')
        ->assertNoJavaScriptErrors();
});

it('stays on the sign-in screen with a wrong password and says so', function (): void {
    visit('/lynx/login')
        ->fill('email', 'ana@example.com')
        ->fill('password', 'not the password')
        ->click('button[type=submit]')
        ->assertPathIs('/lynx/login')
        ->assertSee('These credentials do not match our records.')
        ->assertValue('email', 'ana@example.com');
});
