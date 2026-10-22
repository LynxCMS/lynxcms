<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Support\Facades\Notification;

/**
 * Emails the invitation. On demand, to the address, so the app's user model need not be notifiable.
 *
 * @internal
 */
final class SendInvitation
{
    public function handle(UserInvited $event): void
    {
        Notification::route('mail', $event->email)
            ->notify(new InvitationNotification(Invitations::url($event->token, $event->email), $event->name, Invitations::days()));
    }
}
