<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use LynxCms\Admin\Auth\ResetUserPassword;
use LynxCms\Admin\Auth\TemporaryPassword;
use LynxCms\Core\Support\Value;

/**
 * Choosing one's own password after signing in with a temporary one (ADR-0126). The rules are
 * the app's Password::defaults(), as for invitations, and the temporary password can't be kept.
 *
 * @internal
 */
final class NewPasswordController
{
    public function show(Request $request): View|RedirectResponse
    {
        return $this->temporary($request) === null
            ? redirect()->route('lynx.admin.pages')
            : view('lynx-admin::auth.new-password', ['name' => Value::nullableString(data_get($request->user(), 'name'))]);
    }

    public function store(Request $request, ResetUserPassword $passwords): RedirectResponse
    {
        $user = $request->user();
        $temporary = $this->temporary($request);

        if (! $user instanceof Authenticatable || $temporary === null) {
            return redirect()->route('lynx.admin.pages');
        }

        if (Hash::check($request->string('password')->toString(), $user->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => __('lynx-admin::auth.new_password.same')]);
        }

        $passwords->reset($user, [
            'password' => $request->string('password')->toString(),
            'password_confirmation' => $request->string('password_confirmation')->toString(),
        ]);
        // As after a reset or an invitation: ClearInvitation ends the temporary password, and the
        // app's own listeners hear that the password changed.
        event(new PasswordReset($user));
        $request->session()->regenerate();

        return redirect()->route('lynx.admin.pages');
    }

    private function temporary(Request $request): ?TemporaryPassword
    {
        $id = $request->user()?->getAuthIdentifier();

        return $id === null ? null : TemporaryPassword::query()->where('user_id', Value::string($id))->first();
    }
}
