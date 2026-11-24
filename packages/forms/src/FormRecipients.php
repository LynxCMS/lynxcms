<?php

declare(strict_types=1);

namespace LynxCms\Forms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification as MailNotification;
use Illuminate\Support\Facades\Notification;
use LynxCms\Core\Permissions\PermissionHolders;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Definition\Form;

/**
 * Who hears about a form's submissions: its recipients, else the users who hold forms.view, else
 * the addresses in lynx-forms.notify. The mail for each submission and the daily summary go to
 * the same people.
 *
 * @internal
 */
final readonly class FormRecipients
{
    public function __construct(private PermissionHolders $holders) {}

    public function notify(Form $form, MailNotification $notification): void
    {
        $emails = $form->recipients();
        $users = $emails === null ? $this->users() : [];

        if ($emails === null && $users === []) {
            $emails = Value::strings(config('lynx-forms.notify', []));
        }

        if ($users !== []) {
            Notification::send($users, $notification);
        }

        foreach ($emails ?? [] as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    /** @return list<Model> */
    private function users(): array
    {
        $class = Value::string(config('auth.providers.users.model'));
        $ids = $this->holders->userIds('forms.view');

        return $ids === [] || ! is_subclass_of($class, Model::class) ? [] : array_values($class::query()->whereKey($ids)->get()->all());
    }
}
