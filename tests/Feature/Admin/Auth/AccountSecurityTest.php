<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Passkeys\Passkey;
use Livewire\Livewire;
use LynxCms\Admin\Auth\TwoFactorState;
use LynxCms\Admin\Livewire\AccountSecurity;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;

beforeEach(function (): void {
    courseSite();
    $this->user = User::query()->forceCreate(['name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => Hash::make('correct horse')]);
    $this->actingAs($this->user);
});

/** The current code of the user's authenticator app. */
function totp(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp(decrypt((string) $user->fresh()?->two_factor_secret));
}

it('adds the two-factor columns and the passkeys table', function (): void {
    expect(Schema::hasColumns('users', ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']))->toBeTrue()
        ->and(Schema::hasTable('passkeys'))->toBeTrue();
});

it('asks for the password before showing the account security screen', function (): void {
    $this->get('/lynx/account/security')->assertRedirect('/lynx/user/confirm-password');
    $this->withSession(['auth.password_confirmed_at' => time()])->get('/lynx/account/security')
        ->assertOk()->assertSeeLivewire(AccountSecurity::class)->assertSee('Two-factor authentication')->assertSee('Passkeys');
});

it('links the account screen from the sidebar', function (): void {
    $this->get('/lynx/pages')->assertSee(route('lynx.admin.account'));
});

it('changes the password after checking the current one', function (): void {
    Livewire::test(AccountSecurity::class)
        ->set('current_password', 'wrong')->set('password', 'a long new password')->set('password_confirmation', 'a long new password')
        ->call('updatePassword')->assertHasErrors('current_password');

    Livewire::test(AccountSecurity::class)
        ->set('current_password', 'correct horse')->set('password', 'a long new password')->set('password_confirmation', 'a long new password')
        ->call('updatePassword')->assertHasNoErrors()->assertSet('status', 'Password changed.');

    expect(Hash::check('a long new password', (string) $this->user->fresh()?->password))->toBeTrue();
});

it('changes the password through the app\'s own UpdatesUserPasswords when the app has one', function (): void {
    $calls = [];
    app()->instance(UpdatesUserPasswords::class, new class($calls) implements UpdatesUserPasswords
    {
        /** @param  list<array<string, mixed>>  $calls */
        public function __construct(private array &$calls) {}

        /** @param  array<string, mixed>  $input */
        public function update(mixed $user, array $input): void
        {
            $this->calls[] = $input;
        }
    });

    Livewire::test(AccountSecurity::class)
        ->set('current_password', 'correct horse')->set('password', 'Another-Password-1')->set('password_confirmation', 'Another-Password-1')
        ->call('updatePassword')->assertHasNoErrors()->assertSet('status', 'Password changed.');

    expect($calls)->toBe([['current_password' => 'correct horse', 'password' => 'Another-Password-1', 'password_confirmation' => 'Another-Password-1']])
        ->and(Hash::check('correct horse', (string) $this->user->fresh()?->password))->toBeTrue();
});

it('shows the errors the app\'s UpdatesUserPasswords raises on the form\'s fields', function (): void {
    app()->instance(UpdatesUserPasswords::class, new class implements UpdatesUserPasswords
    {
        /** @param  array<string, mixed>  $input */
        public function update(mixed $user, array $input): void
        {
            Validator::make($input, ['password' => ['min:40']])->validateWithBag('updatePassword');
        }
    });

    Livewire::test(AccountSecurity::class)
        ->set('current_password', 'correct horse')->set('password', 'short-but-fine')->set('password_confirmation', 'short-but-fine')
        ->call('updatePassword')->assertHasErrors('password')->assertSet('current_password', '');
});

it('enables two-factor authentication once a code confirms it', function (): void {
    $screen = Livewire::test(AccountSecurity::class)->call('enableTwoFactor')->assertSee('Scan the QR code');

    $screen->set('code', '000000')->call('confirmTwoFactor')->assertHasErrors('code');
    expect($this->user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $screen->set('code', totp($this->user))->call('confirmTwoFactor')->assertHasNoErrors()->assertSee('Recovery codes');
    expect($this->user->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('disables two-factor authentication', function (): void {
    $screen = Livewire::test(AccountSecurity::class)->call('enableTwoFactor');
    $screen->set('code', totp($this->user))->call('confirmTwoFactor')->call('disableTwoFactor');

    expect($this->user->fresh()?->two_factor_secret)->toBeNull();
});

it('drops a setup that was never confirmed', function (): void {
    Livewire::test(AccountSecurity::class)->call('enableTwoFactor');
    expect($this->user->fresh()?->two_factor_secret)->not->toBeNull();

    Livewire::test(AccountSecurity::class)->assertSee('Turn on');
    expect($this->user->fresh()?->two_factor_secret)->toBeNull();
});

it('asks for the code when signing in with two-factor authentication', function (): void {
    $screen = Livewire::test(AccountSecurity::class)->call('enableTwoFactor');
    $screen->set('code', totp($this->user))->call('confirmTwoFactor');
    auth()->logout();

    $this->post('/lynx/login', ['email' => 'ana@example.com', 'password' => 'correct horse'])->assertRedirect('/lynx/two-factor-challenge');
    $this->get('/lynx/two-factor-challenge')->assertOk()->assertSee('Authentication code');
    // The confirming code cannot be used twice, so sign in with a recovery code.
    $recoveryCode = TwoFactorState::recoveryCodes($this->user->fresh() ?? $this->user)[0];
    $this->post('/lynx/two-factor-challenge', ['recovery_code' => $recoveryCode])->assertRedirect('/lynx/pages');
    $this->assertAuthenticatedAs($this->user);
});

it('lists and removes passkeys', function (): void {
    $passkey = $this->user->passkeys()->create(['name' => 'Chrome on Mac', 'credential_id' => 'abc', 'credential' => ['aaguid' => null]]);

    Livewire::test(AccountSecurity::class)
        ->assertSee('Chrome on Mac')
        ->call('deletePasskey', $passkey->id)
        ->assertDontSee('Chrome on Mac');

    expect(Passkey::query()->count())->toBe(0);
});

it('never removes another user\'s passkey', function (): void {
    $bruno = User::query()->forceCreate(['name' => 'Bruno', 'email' => 'bruno@example.com', 'password' => Hash::make('x')]);
    $passkey = $bruno->passkeys()->create(['name' => 'Bruno key', 'credential_id' => 'xyz', 'credential' => []]);

    Livewire::test(AccountSecurity::class)->call('deletePasskey', $passkey->id)->assertNotFound();
});
