<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Notifications\SubmissionConfirmation;
use LynxCms\Forms\Notifications\SubmissionReceived;
use LynxCms\Tests\Fixtures\Forms\QuoteForm;
use Workbench\App\Models\User;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    courseSite();
    lynx()->forms()->register(QuoteForm::class);
    foreach ([1 => 'Admin', 2 => 'Producer', 3 => 'Editor'] as $id => $name) {
        DB::table('users')->insert(['id' => $id, 'name' => $name, 'email' => strtolower($name).'@example.com', 'password' => 'x']);
    }
    lynx()->run(SaveRole::class, ['name' => 'production', 'grants' => [['permission' => 'forms.view']]]);
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'pages.view']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '2', 'roles' => ['production']]);
    lynx()->run(AssignRoles::class, ['user' => '3', 'roles' => ['editor']]);
});

function sendQuote(): void
{
    lynx()->run(SubmitForm::class, ['form' => 'quote', 'language' => 'pt', 'data' => [
        'type' => 'film', 'name' => 'Ana Lima', 'email' => 'ana@example.com', 'consent' => true,
    ]]);
}

it('tells the users who may see submissions', function (): void {
    sendQuote();

    Notification::assertSentTo(User::query()->find(1), SubmissionReceived::class);
    Notification::assertSentTo(User::query()->find(2), SubmissionReceived::class);
    Notification::assertNotSentTo(User::query()->find(3), SubmissionReceived::class);
});

it('sends the submitter a confirmation in their language', function (): void {
    sendQuote();

    Notification::assertSentOnDemand(SubmissionConfirmation::class, fn (SubmissionConfirmation $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'ana@example.com' && $notification->locale === 'pt-PT' && $notification->toMail($notifiable)->introLines[0] === 'Recebemos o seu pedido.');
});

it('mails the fields and a link to the submission', function (): void {
    sendQuote();

    Notification::assertSentTo(User::query()->find(2), SubmissionReceived::class, function (SubmissionReceived $notification): bool {
        $mail = $notification->toMail(User::query()->find(2));

        return str_contains($mail->subject, 'Quote request') && in_array('**Name:** Ana Lima', $mail->introLines, true);
    });
});

it('mails the form\'s own recipients instead of the team', function (): void {
    lynx()->forms()->register(new class extends QuoteForm
    {
        public function recipients(): array
        {
            return ['studio@example.com'];
        }
    });
    sendQuote();

    Notification::assertNotSentTo(User::query()->find(2), SubmissionReceived::class);
    Notification::assertSentOnDemand(SubmissionReceived::class, fn ($notification, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'studio@example.com');
});

it('falls back to the configured addresses when nobody may see submissions', function (): void {
    DB::table('lynx_role_user')->delete();
    config(['lynx-forms.notify' => ['ops@example.com']]);
    sendQuote();

    Notification::assertSentOnDemand(SubmissionReceived::class, fn ($notification, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'ops@example.com');
});

it('keeps a label\'s own punctuation in the mail', function (): void {
    lynx()->forms()->register(new class extends QuoteForm
    {
        public function fields(): array
        {
            return [
                ...array_filter(parent::fields(), fn (FormField $field): bool => $field->name !== 'name'),
                FormField::text('name')->label('What is your name?')->required(),
            ];
        }
    });
    sendQuote();

    Notification::assertSentTo(User::query()->find(2), SubmissionReceived::class, function (SubmissionReceived $notification): bool {
        $lines = $notification->toMail(User::query()->find(2))->introLines;

        return in_array('**What is your name?** Ana Lima', $lines, true) && in_array('**I agree to be contacted:** Yes', $lines, true);
    });
});
