<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationEvent;
use LynxCms\Admin\AdminServiceProvider;

/**
 * Emails a user when two-factor authentication is turned on or off for their account (ADR-0135).
 * Only on sites that send email, and only to users who can open the admin: an app's members, who
 * may use the app's own two-factor screens, hear from the app instead.
 *
 * @internal
 */
final class SendTwoFactorNotice
{
    public function handle(TwoFactorAuthenticationEvent $event): void
    {
        // Fortify types the user as the app's App\Models\User, which a package can't assume.
        $user = data_get($event, 'user');

        if (! AdminAuth::sendsMail() || ! $user instanceof Authenticatable || ! method_exists($user, 'routeNotificationFor') || ! Gate::forUser($user)->allows(AdminServiceProvider::GATE)) {
            return;
        }

        Notification::send($user, new TwoFactorChanged($event instanceof TwoFactorAuthenticationConfirmed, now()->isoFormat('llll')));
    }
}
