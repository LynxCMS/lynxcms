<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use LynxCms\Admin\Auth\UpdateUserPassword;

/**
 * Account security: change the password, after checking the current one. Fortify's
 * UpdatesUserPasswords does the change: the app's own when it configures Fortify, Lynx's
 * UpdateUserPassword otherwise. The fields are named as Fortify's input, so its errors land on them.
 *
 * @internal
 */
trait ChangesPassword
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $this->status = null;
        $user = auth()->user();
        abort_unless($user instanceof Authenticatable, 403);

        try {
            $this->passwordUpdater()->update($user, [
                'current_password' => $this->current_password,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ]);
        } finally {
            $this->reset('current_password', 'password', 'password_confirmation');
        }

        $this->notify(__('lynx-admin::account.password.changed'));
    }

    private function passwordUpdater(): UpdatesUserPasswords
    {
        return app()->bound(UpdatesUserPasswords::class) ? app(UpdatesUserPasswords::class) : app(UpdateUserPassword::class);
    }
}
