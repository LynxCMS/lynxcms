<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Auth\Invitations;
use LynxCms\Admin\Auth\ResetUserPassword;
use LynxCms\Core\Support\Value;

/**
 * Accepting an invitation (ADR-0041): the invited user chooses a password and is signed in.
 *
 * @internal
 */
final class InvitationController
{
    public function show(Request $request, string $token): View
    {
        $email = $request->string('email')->toString();
        $user = Auth::getProvider()->retrieveByCredentials(['email' => $email]);

        return view('lynx-admin::auth.invitation', [
            'token' => $token,
            'email' => $email,
            'name' => $user === null ? null : Value::nullableString(data_get($user, 'name')),
        ]);
    }

    public function store(Request $request, ResetUserPassword $passwords): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::default(), 'confirmed'],
        ]);
        $input = ['password' => $request->string('password')->toString(), 'password_confirmation' => $request->string('password_confirmation')->toString()];
        $accepted = null;

        $status = Invitations::broker()->reset(
            ['token' => $request->string('token')->toString(), 'email' => $request->string('email')->toString()] + $input,
            function (CanResetPassword $user) use ($passwords, $input, &$accepted): void {
                if (! $user instanceof Authenticatable) {
                    return;
                }

                $passwords->reset($user, $input);
                event(new PasswordReset($user));
                $accepted = $user;
            },
        );

        if ($status !== Password::PASSWORD_RESET || ! $accepted instanceof Authenticatable) {
            throw ValidationException::withMessages(['email' => $status === Password::INVALID_TOKEN ? 'This invitation has expired or was already used. Ask for a new one.' : __(Value::string($status))]);
        }

        Auth::login($accepted);
        $request->session()->regenerate();

        return redirect()->route('lynx.admin.pages');
    }
}
