<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures\AppAuth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use LynxCms\Core\Support\Value;
use Workbench\App\Models\User;

/** The app's sign-up, as Fortify's starter code writes it. */
final class CreateNewUser implements CreatesNewUsers
{
    /** @param  array<string, mixed>  $input */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        return User::query()->forceCreate([
            'name' => Value::string($input['name']),
            'email' => Value::string($input['email']),
            'password' => Hash::make(Value::string($input['password'])),
        ]);
    }
}
