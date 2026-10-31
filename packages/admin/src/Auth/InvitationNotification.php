<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LynxCms\Core\Support\Value;

/**
 * The invitation email: a link to choose a password, valid for some days.
 *
 * @api
 */
final class InvitationNotification extends Notification
{
    public function __construct(
        public readonly string $url,
        public readonly string $name,
        public readonly int $days,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = Value::string(config('app.name'), 'Lynx');

        return (new MailMessage)
            ->subject(Value::string(__('lynx-admin::users.mail.subject', ['site' => $site])))
            ->greeting(Value::string(__('lynx-admin::users.mail.greeting', ['name' => $this->name])))
            ->line(__('lynx-admin::users.mail.intro', ['site' => $site]))
            ->action(Value::string(__('lynx-admin::users.mail.action')), $this->url)
            ->line(__('lynx-admin::users.mail.outro', ['days' => $this->days]));
    }
}
