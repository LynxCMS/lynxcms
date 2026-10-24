<?php

declare(strict_types=1);

namespace LynxCms\Forms\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\SubmissionPresenter;

/**
 * Mail to the people who handle a form: its answers and a link to the submission in the admin.
 * Files stay on the private disk; the mail only names them. Answers are plain text, never Markdown.
 */
final class SubmissionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Submission $submission)
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
        $form = lynx()->forms()->get($this->submission->form);
        $label = $form instanceof Form ? $form->text('label')->in() : LocalizedText::from($this->submission->form)->in();
        $mail = (new MailMessage)->subject(__('lynx-forms::forms.mail.subject', ['form' => $label]));

        foreach (app(SubmissionPresenter::class)->lines($this->submission) as $line) {
            // "Name:", but "Which project?" and "I agree." keep their own punctuation.
            $label = preg_match('/[?!.:]$/u', $line['label']) === 1 ? $line['label'] : $line['label'].':';
            $mail->line("**{$label}** ".PlainMarkdown::escape($line['value']));
        }

        return Route::has('lynx.admin.forms.show')
            ? $mail->action(__('lynx-forms::forms.mail.open'), route('lynx.admin.forms.show', $this->submission->uuid))
            : $mail;
    }
}
