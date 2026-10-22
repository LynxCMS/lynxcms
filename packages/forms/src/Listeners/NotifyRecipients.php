<?php

declare(strict_types=1);

namespace LynxCms\Forms\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use LynxCms\Core\Permissions\PermissionHolders;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Events\FormSubmitted;
use LynxCms\Forms\FormRegistry;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\Notifications\SubmissionConfirmation;
use LynxCms\Forms\Notifications\SubmissionReceived;

/**
 * After a submission commits: mails the form's recipients, or the users who hold forms.view, or
 * the configured fallback addresses; confirms to the visitor when the form has an auto reply; and
 * runs the form's afterSubmit() hook.
 *
 * @internal
 */
final readonly class NotifyRecipients
{
    public function __construct(private FormRegistry $forms, private PermissionHolders $holders) {}

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
        $emails = $form->recipients();
        $users = $emails === null ? $this->users() : [];

        if ($emails === null && $users === []) {
            $emails = Value::strings(config('lynx-forms.notify', []));
        }

        if ($users !== []) {
            Notification::send($users, new SubmissionReceived($submission));
        }

        foreach ($emails ?? [] as $email) {
            Notification::route('mail', $email)->notify(new SubmissionReceived($submission));
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

        $locale = $submission->language()?->appLocale();
        Notification::route('mail', $email)->notify(new SubmissionConfirmation($submission, $text, $form->label())->locale($locale));
    }

    /**
     * @return list<Model>
     */
    private function users(): array
    {
        $class = Value::string(config('auth.providers.users.model'));
        $ids = $this->holders->userIds('forms.view');

        return $ids === [] || ! is_subclass_of($class, Model::class) ? [] : array_values($class::query()->whereKey($ids)->get()->all());
    }
}
