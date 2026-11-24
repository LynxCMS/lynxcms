<?php

declare(strict_types=1);

namespace LynxCms\Forms\Listeners;

use Illuminate\Support\Facades\Notification;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Events\FormSubmitted;
use LynxCms\Forms\FormRecipients;
use LynxCms\Forms\FormRegistry;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\Notifications\SubmissionConfirmation;
use LynxCms\Forms\Notifications\SubmissionReceived;

/**
 * After a submission commits: mails the people who handle the form (FormRecipients), unless the
 * form sends a daily summary; confirms to the visitor when the form has an auto reply; and
 * runs the form's afterSubmit() hook.
 *
 * @internal
 */
final readonly class NotifyRecipients
{
    public function __construct(private FormRegistry $forms, private FormRecipients $recipients) {}

    public function handle(FormSubmitted $event): void
    {
        $submission = Submission::query()->with('files')->find($event->submissionId);
        $form = $submission === null ? null : $this->forms->get($submission->form);

        if (! $submission instanceof Submission || ! $form instanceof Form) {
            return;
        }

        $this->notifyTeam($form, $submission);
        $this->confirm($form, $submission);
        $form->afterSubmit($submission);
    }

    private function notifyTeam(Form $form, Submission $submission): void
    {
        // A form with a daily summary is mailed by lynx:forms:digest instead.
        if (! $form->digest()) {
            $this->recipients->notify($form, new SubmissionReceived($submission));
        }
    }

    private function confirm(Form $form, Submission $submission): void
    {
        $text = $form->autoReply();
        $field = $form->replyToField();
        $email = $field === null ? null : ($submission->data[$field->name] ?? null);

        if ($text === null || ! is_string($email) || $email === '') {
            return;
        }

        $confirmation = new SubmissionConfirmation($submission, $text, $form->label());
        $locale = $submission->language()?->appLocale();
        Notification::route('mail', $email)->notify($locale === null ? $confirmation : $confirmation->locale($locale));
    }
}
