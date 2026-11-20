<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LynxCms\Core\Support\Value;

/**
 * The email telling a user that two-factor authentication was turned on or off for their account
 * (ADR-0135), whoever did it: the user, a superuser's reset or lynx:two-factor:reset. Queued, so
 * a mail server that is down never stops the change itself.
 *
 * @internal
 */
final class TwoFactorChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly bool $isOn, public readonly string $when)
    {
        $this->afterCommit = true;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $state = $this->isOn ? 'on' : 'off';
        $replace = ['site' => Value::string(config('app.name'), 'Lynx'), 'when' => $this->when];

        return (new MailMessage)
            ->subject(Value::string(__("lynx-admin::account.two_factor.mail.subject_{$state}", $replace)))
            ->line(Value::string(__("lynx-admin::account.two_factor.mail.{$state}", $replace)))
            ->line(Value::string(__('lynx-admin::account.two_factor.mail.not_you', $replace)));
    }
}
