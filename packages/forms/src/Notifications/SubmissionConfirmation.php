<?php

declare(strict_types=1);

namespace LynxCms\Forms\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Forms\Models\Submission;

/**
 * The confirmation mailed to the visitor who sent a form, in their language (Form::autoReply()).
 */
final class SubmissionConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string|array<string, string>  $text
     * @param  string|array<string, string>  $formLabel
     */
    public function __construct(public readonly Submission $submission, public readonly string|array $text, public readonly string|array $formLabel)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $code = $this->submission->language()?->code;

        return (new MailMessage)
            ->subject(LocalizedText::from($this->formLabel)->in($code !== null ? (string) $code : null))
            ->line(LocalizedText::from($this->text)->in($code !== null ? (string) $code : null));
    }
}
