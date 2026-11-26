<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use LynxCms\Core\Support\Value;

/**
 * Account security sits behind password.confirm, and Livewire's updates skip route middleware, so
 * each action that changes how the user signs in checks the confirmation itself. Once it is older
 * than auth.password_timeout, as Laravel's RequirePassword counts it, the user goes to confirm the
 * password again and comes back to the screen.
 *
 * @internal
 */
trait ConfirmsPasswordAgain
{
    /** False after sending the user to confirm the password: the action must stop. */
    private function hasRecentPassword(): bool
    {
        $confirmedAt = Value::int(session('auth.password_confirmed_at', 0));

        if (time() - $confirmedAt <= Value::int(config('auth.password_timeout', 10800))) {
            return true;
        }

        redirect()->setIntendedUrl(route('lynx.admin.account'));
        $this->redirectRoute('password.confirm');

        return false;
    }
}
