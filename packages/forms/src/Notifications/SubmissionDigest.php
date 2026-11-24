<?php

declare(strict_types=1);

namespace LynxCms\Forms\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use LynxCms\Core\Support\LocalizedNumber;

/**
 * The daily summary of a form's new submissions (lynx:forms:digest): how many, the first lines
 * of the latest ones, and a link to them in the inbox. Answers are plain text, never Markdown.
 *
 * @internal
 */
final class SubmissionDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /** Submissions the mail lists; the link shows them all. */
    public const int LISTED = 20;

    /**
     * @param  list<string>  $summaries  the latest submissions' first answers, newest first
     */
    public function __construct(
        public readonly string $form,
        public readonly string $label,
        public readonly int $count,
        public readonly array $summaries,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(trans_choice('lynx-forms::forms.digest.subject', $this->count, ['count' => LocalizedNumber::format($this->count), 'form' => $this->label]))
            ->line(trans_choice('lynx-forms::forms.digest.intro', $this->count, ['count' => LocalizedNumber::format($this->count), 'form' => $this->label]));

        foreach ($this->summaries as $summary) {
            $mail->line('- '.PlainMarkdown::escape($summary));
        }

        if ($this->count > count($this->summaries)) {
            $mail->line(__('lynx-forms::forms.digest.more', ['count' => LocalizedNumber::format($this->count - count($this->summaries))]));
        }

        return Route::has('lynx.admin.forms')
            ? $mail->action(__('lynx-forms::forms.digest.open'), route('lynx.admin.forms', ['form' => $this->form, 'show' => 'new']))
            : $mail;
    }
}
