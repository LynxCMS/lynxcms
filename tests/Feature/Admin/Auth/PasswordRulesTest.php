<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use LynxCms\Admin\Auth\Invitations;
use LynxCms\Admin\Auth\PasswordRules;
use LynxCms\Admin\Livewire\AccountSecurity;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use Workbench\App\Models\User;

/*
 * Every form that sets a password lists the app's password rules (Password::defaults()), which
 * the browser ticks as the user types. The server still checks them when the form is sent.
 */

beforeEach(function (): void {
    courseSite();
    Password::defaults(fn (): Password => Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised());
});

afterEach(function (): void {
    Password::defaults(Password::min(8));
});

it('lists the rules the app sets, with the patterns the browser checks', function (): void {
    expect(array_column((new PasswordRules)->all(), 'key'))->toBe(['min', 'mixedCase', 'numbers', 'symbols', 'uncompromised'])
        ->and((new PasswordRules)->all()[0])->toMatchArray(['label' => 'At least 12 characters', 'min' => 12]);
});

it('lists only the length when the app sets no rules', function (): void {
    Password::defaults(Password::min(8));

    expect(array_column((new PasswordRules)->all(), 'label'))->toBe(['At least 8 characters']);
});

it('shows the rules on the reset, invitation and new password screens', function (string $url): void {
    $this->get($url)->assertOk()
        ->assertSee('data-password-rules', escape: false)
        ->assertSee('At least 12 characters')
        ->assertSee('Upper and lower case letters')
        ->assertSee('Not found in known data breaches');
})->with([
    'reset' => '/lynx/reset-password/a-token?email=ana%40example.com',
    // The invitation screen asks for a password only for a valid invitation.
    'invitation' => function (): string {
        $ana = User::query()->forceCreate(['name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => Hash::make('correct horse')]);

        return '/lynx/invitation/'.Invitations::broker()->createToken($ana).'?email=ana%40example.com';
    },
]);

it('shows the rules where a signed-in user changes their password', function (): void {
    $ana = User::query()->forceCreate(['name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => Hash::make('correct horse')]);
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(AssignRoles::class, ['user' => (string) $ana->id, 'roles' => ['superuser']]);
    $this->actingAs($ana);

    Livewire::test(AccountSecurity::class)->assertSee('At least 12 characters')->assertSee('data-password-rules', escape: false);
});
