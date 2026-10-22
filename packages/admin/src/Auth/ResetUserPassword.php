<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use LynxCms\Core\Support\Value;

/**
 * Sets the password chosen on the reset (or invitation) screen. The rules are the app's
 * Password::defaults().
 *
 * @internal
 */
final class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function reset(Authenticatable $user, array $input): void
    {
        Validator::make($input, ['password' => ['required', 'string', Password::default(), 'confirmed']])->validate();

        if ($user instanceof Model) {
            $user->forceFill(['password' => Hash::make(Value::string($input['password']))])->save();
        }
    }
}
