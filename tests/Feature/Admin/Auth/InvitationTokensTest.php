<?php

declare(strict_types=1);

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use LynxCms\Admin\Auth\Invitations;
use LynxCms\Core\Actions\Roles\AssignRoles;
use Workbench\App\Models\User;

/*
 * Invitation links last days and password reset links an hour, so their tokens live apart:
 * neither kind opens the other's form.
 */

beforeEach(function (): void {
    installLynx();
    $this->bruno = User::query()->forceCreate(['name' => 'Bruno', 'email' => 'bruno@example.com', 'password' => 'x']);
    lynx()->run(AssignRoles::class, ['user' => (string) $this->bruno->id, 'roles' => ['editor']]);
});

function acceptInvitation(mixed $test, string $token): mixed
{
    return $test->post('/lynx/invitation', ['token' => $token, 'email' => 'bruno@example.com', 'password' => 'a long new password', 'password_confirmation' => 'a long new password']);
}

it('keeps invitation tokens in their own table', function (): void {
    Invitations::broker()->createToken($this->bruno);

    expect(DB::table('lynx_invitation_tokens')->where('email', 'bruno@example.com')->exists())->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', 'bruno@example.com')->exists())->toBeFalse();
});

it('refuses a password reset token on the invitation form', function (): void {
    $reset = Password::broker()->createToken($this->bruno);

    acceptInvitation($this, $reset)->assertSessionHasErrors('email');
});

it('refuses an invitation token for a password reset', function (): void {
    $invitation = Invitations::broker()->createToken($this->bruno);

    expect(Password::broker()->tokenExists($this->bruno, $invitation))->toBeFalse();
    acceptInvitation($this, $invitation)->assertRedirect('/lynx/pages');
});

it('ends the invitation when the user resets their password', function (): void {
    $invitation = Invitations::broker()->createToken($this->bruno);
    $reset = Password::broker()->createToken($this->bruno);

    Password::broker()->reset(['email' => 'bruno@example.com', 'token' => $reset, 'password' => 'a long new password', 'password_confirmation' => 'a long new password'], function (User $user): void {
        $user->forceFill(['password' => 'a long new password'])->save();
        event(new PasswordReset($user));
    });

    expect(Invitations::broker()->tokenExists($this->bruno, $invitation))->toBeFalse();
});
