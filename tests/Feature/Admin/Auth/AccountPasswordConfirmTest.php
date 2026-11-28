<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Laravel\Passkeys\Passkey;
use Livewire\Livewire;
use LynxCms\Admin\Auth\TwoFactorState;
use LynxCms\Admin\Livewire\AccountSecurity;
use Workbench\App\Models\User;

/*
 * The account security screen opens after the password is confirmed (password.confirm). Livewire's
 * updates skip that route middleware, so the screen's actions check the confirmation themselves:
 * once it expired, they send the user to confirm the password again and change nothing.
 */

beforeEach(function (): void {
    courseSite();
    $this->user = User::query()->forceCreate(['name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => Hash::make('correct horse')]);
    $this->actingAs($this->user);
});

function expiredConfirmation(): void
{
    session(['auth.password_confirmed_at' => time() - (int) config('auth.password_timeout', 10800) - 1]);
}

it('asks for the password again before regenerating recovery codes', function (): void {
    session(['auth.password_confirmed_at' => time()]);
    $screen = Livewire::test(AccountSecurity::class)->call('enableTwoFactor');
    $this->user->forceFill(['two_factor_confirmed_at' => now()])->save();
    $codes = TwoFactorState::recoveryCodes($this->user->refresh());
    expiredConfirmation();

    $screen->call('regenerateRecoveryCodes')
        ->assertRedirect(route('password.confirm'))
        ->assertSet('recoveryCodes', []);

    expect(TwoFactorState::recoveryCodes($this->user->refresh()))->toBe($codes);
});

it('asks for the password again before turning two-factor authentication on or off', function (string $method): void {
    expiredConfirmation();

    Livewire::test(AccountSecurity::class)->call($method)->assertRedirect(route('password.confirm'));

    expect($this->user->refresh()->two_factor_secret)->toBeNull();
})->with(['enableTwoFactor', 'disableTwoFactor']);

it('asks for the password again before removing a passkey', function (): void {
    $passkey = $this->user->passkeys()->create(['name' => 'Laptop', 'credential_id' => 'abc', 'credential' => ['aaguid' => null]]);
    expiredConfirmation();

    Livewire::test(AccountSecurity::class)->call('deletePasskey', $passkey->id)->assertRedirect(route('password.confirm'));

    expect(Passkey::query()->whereKey($passkey->id)->exists())->toBeTrue();
});

it('acts while the confirmation is recent', function (): void {
    session(['auth.password_confirmed_at' => time()]);

    Livewire::test(AccountSecurity::class)->call('enableTwoFactor')->assertNoRedirect()->assertSet('isSettingUpTwoFactor', true);
});
