<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use LynxCms\Core\Support\Value;

/**
 * An invited user who sets a password (from the invitation or a password reset) is no longer
 * pending, and their invitation link stops working.
 *
 * @internal
 */
final class ClearInvitation
{
    public function handle(PasswordReset $event): void
    {
        Invitation::query()->where('user_id', Value::string($event->user->getAuthIdentifier()))->delete();

        if ($event->user instanceof CanResetPassword) {
            Invitations::broker()->getRepository()->delete($event->user);
        }
    }
}
