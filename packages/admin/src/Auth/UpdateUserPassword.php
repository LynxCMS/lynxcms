<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use LynxCms\Core\Support\Value;

/**
 * Changes a signed-in user's password on Account security, after checking the current one. The
 * rules are the app's Password::defaults(). Registered with Fortify when Lynx signs users in; an
 * app that configures Fortify itself uses its own UpdatesUserPasswords instead.
 *
 * @internal
 */
final class UpdateUserPassword implements UpdatesUserPasswords
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Authenticatable $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();

        if ($user instanceof Model) {
            $user->forceFill(['password' => Hash::make(Value::string($input['password']))])->save();
        }
    }
}
