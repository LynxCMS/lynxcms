<?php

declare(strict_types=1);

use Pest\Browser\Api\AwaitableWebpage;

/*
 * Signing in with two-factor authentication turned on: after the password, the challenge screen
 * asks for the code from the authenticator app, or one of the recovery codes.
 */

beforeEach(function (): void {
    signInSite();
    $this->ana = withTwoFactor(browserUser('ana@example.com'));
});

function passwordThenChallenge(): AwaitableWebpage
{
    return visit('/lynx/login')
        ->fill('email', 'ana@example.com')
        ->fill('password', BROWSER_PASSWORD)
        ->click('button[type=submit]')
        ->assertPathIs('/lynx/two-factor-challenge')
        ->assertSee('Enter the code from your authenticator app');
}

it('asks for the code after the password and signs in with it', function (): void {
    passwordThenChallenge()
        ->fill('code', currentCode($this->ana))
        ->click('button[type=submit]')
        ->assertPathIsNot('/lynx/two-factor-challenge')
        ->assertSee('Sign out')
        ->assertNoJavaScriptErrors();
});

it('signs in with a recovery code, which then no longer works', function (): void {
    $recoveryCode = $this->ana->recoveryCodes()[0];

    passwordThenChallenge()
        ->press('Use a recovery code')
        ->fill('recovery_code', $recoveryCode)
        ->click('button[type=submit]')
        ->assertSee('Sign out');

    expect($this->ana->refresh()->recoveryCodes())->not->toContain($recoveryCode);
});

it('stays on the challenge with a wrong code', function (): void {
    passwordThenChallenge()
        ->fill('code', '000000')
        ->click('button[type=submit]')
        ->assertPathIs('/lynx/two-factor-challenge')
        ->assertDontSee('Sign out');
});
