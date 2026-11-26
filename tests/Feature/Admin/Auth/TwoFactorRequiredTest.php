<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Livewire;
use LynxCms\Admin\Auth\CreateUserWithTemporaryPassword;
use LynxCms\Admin\Auth\ResetTwoFactor;
use LynxCms\Admin\Doctor\TwoFactorAvailable;
use LynxCms\Admin\Livewire\AccountSecurity;
use LynxCms\Admin\Livewire\UserRoles;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;

/*
 * Two-factor authentication for everyone who opens the admin (ADR-0128): a user without it sets
 * it up before any other admin screen. Members, who never open the admin, are not asked.
 */

beforeEach(function (): void {
    courseSite();
    config(['lynx-admin.auth.require_two_factor' => true]);
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.edit']]]);
    $this->ana = adminUser('ana@example.com', 'superuser');
});

function adminUser(string $email, string $role): User
{
    $user = User::query()->forceCreate(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make('correct horse battery')]);
    lynx()->actingAs(Actor::system(), fn (): ActionResult => lynx()->run(AssignRoles::class, ['user' => (string) $user->id, 'roles' => [$role]]));

    return $user;
}

function turnOnTwoFactor(User $user): void
{
    app(EnableTwoFactorAuthentication::class)($user);
    $user->refresh();
    app(ConfirmTwoFactorAuthentication::class)($user, app(Google2FA::class)->getCurrentOtp(decrypt((string) $user->two_factor_secret)));
}

it('sends an admin user without two-factor authentication to set it up first', function (): void {
    $this->actingAs($this->ana);

    $this->get('/lynx/pages')->assertRedirect('/lynx/account/security');
    $this->withSession(['auth.password_confirmed_at' => time()])->get('/lynx/account/security')
        ->assertOk()->assertSee('Two-factor authentication is required for the admin.');
});

it('lets an admin user with two-factor authentication in', function (): void {
    turnOnTwoFactor($this->ana);
    $this->actingAs($this->ana->fresh());

    $this->get('/lynx/pages')->assertOk();
});

it('asks nothing when the site does not require it', function (): void {
    config(['lynx-admin.auth.require_two_factor' => false]);
    $this->actingAs($this->ana);

    $this->get('/lynx/pages')->assertOk();
});

it('asks for the own password before two-factor authentication', function (): void {
    $this->actingAs($this->ana);
    $created = lynx()->run(CreateUserWithTemporaryPassword::class, ['name' => 'Bruno', 'email' => 'bruno@example.com', 'roles' => ['editor']])->value;
    $this->actingAs(User::query()->findOrFail($created['user']));

    $this->get('/lynx/pages')->assertRedirect('/lynx/account/new-password');
    $this->get('/lynx/account/new-password')->assertOk();
});

it('keeps two-factor authentication on while it is required', function (): void {
    turnOnTwoFactor($this->ana);
    $this->actingAs($this->ana->fresh());
    session(['auth.password_confirmed_at' => time()]);

    Livewire::test(AccountSecurity::class)->assertDontSee('Turn off')->call('disableTwoFactor')->assertHasErrors('two_factor');

    expect($this->ana->fresh()?->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('lets a superuser reset another user\'s two-factor authentication', function (): void {
    turnOnTwoFactor($this->ana);
    $bruno = adminUser('bruno@example.com', 'editor');
    turnOnTwoFactor($bruno);
    $this->actingAs($this->ana->fresh());

    Livewire::test(UserRoles::class)
        ->assertSee('Reset two-factor')
        ->call('resetTwoFactor', (string) $bruno->id)
        ->assertSet('status', 'Two-factor authentication reset for Bruno.');

    expect($bruno->fresh()?->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('lets only superusers reset two-factor authentication, and is not an agent tool', function (): void {
    $bruno = adminUser('bruno@example.com', 'editor');
    $this->actingAs($bruno);

    expect(ResetTwoFactor::exposedAsTool())->toBeFalse()
        ->and(app(ActionRegistry::class)->find('users.reset_two_factor'))->toBe(ResetTwoFactor::class);

    lynx()->run(ResetTwoFactor::class, ['user' => (string) $this->ana->id]);
})->throws(AuthorizationException::class);

it('resets two-factor authentication from the console, for a locked out superuser', function (): void {
    turnOnTwoFactor($this->ana);

    $this->artisan('lynx:two-factor:reset', ['email' => 'ana@example.com'])->assertSuccessful();

    expect($this->ana->fresh()?->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('says so when the console finds no such user', function (): void {
    $this->artisan('lynx:two-factor:reset', ['email' => 'nobody@example.com'])->assertFailed();
});

it('warns in lynx:doctor when nobody can set it up, and asks nothing then', function (): void {
    config(['auth.providers.users.model' => Illuminate\Foundation\Auth\User::class]);

    $findings = (new TwoFactorAvailable)->run();

    expect($findings)->toHaveCount(1)->and($findings[0]->message)->toContain('cannot be set up');
    $this->actingAs($this->ana)->get('/lynx/pages')->assertOk();
});
