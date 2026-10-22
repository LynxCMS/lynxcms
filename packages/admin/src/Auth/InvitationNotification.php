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
            ->subject("You are invited to {$site}")
            ->greeting("Hello {$this->name},")
            ->line("You have been invited to manage the content of {$site}. Choose a password to sign in.")
            ->action('Choose your password', $this->url)
            ->line("The link works for {$this->days} days. If you were not expecting this invitation, ignore this email.");
    }
}
