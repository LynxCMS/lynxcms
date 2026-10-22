<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Account security: change the password, after checking the current one.
 *
 * @internal
 */
trait ChangesPassword
{
    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $this->status = null;

        try {
            $this->validate([
                'currentPassword' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::default(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('currentPassword', 'password', 'password_confirmation');

            throw $e;
        }

        $user = auth()->user();
        abort_unless($user instanceof Model, 403);
        $user->forceFill(['password' => Hash::make($this->password)])->save();
        $this->reset('currentPassword', 'password', 'password_confirmation');
        $this->notify('Password changed.');
    }
}
