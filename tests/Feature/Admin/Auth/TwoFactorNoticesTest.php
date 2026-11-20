<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Livewire;
use LynxCms\Admin\Auth\ResetTwoFactor;
use LynxCms\Admin\Auth\TwoFactorChanged;
use LynxCms\Admin\Livewire\UserRoles;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use PragmaRX\Google2FA\Google2FA;
use Workbench\App\Models\User;

/*
 * Two-factor authentication notices (ADR-0135): an admin user hears by email when it is turned on
 * or off for their account, and superusers see on the Users screen who has not set it up yet.
 */

beforeEach(function (): void {
    courseSite();
    Notification::fake();
    config(['lynx-admin.auth.require_two_factor' => true]);
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.edit']]]);
    $this->ana = noticeUser('ana@example.com', 'superuser');
});

function noticeUser(string $email, ?string $role): User
{
    $user = User::query()->forceCreate(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make('correct horse battery')]);

    if ($role !== null) {
        lynx()->actingAs(Actor::system(), fn (): ActionResult => lynx()->run(AssignRoles::class, ['user' => (string) $user->id, 'roles' => [$role]]));
    }

    return $user;
}

/** @return list<string> the users the screen marks as without two-factor authentication */
function missingTwoFactor(string $html): array
{
    preg_match_all('/data-no-two-factor="([^"]*)"/', $html, $matches);

    return $matches[1];
}

function confirmTwoFactor(User $user): void
{
    app(EnableTwoFactorAuthentication::class)($user);
    $user->refresh();
    app(ConfirmTwoFactorAuthentication::class)($user, app(Google2FA::class)->getCurrentOtp(decrypt((string) $user->two_factor_secret)));
}

it('emails an admin user when two-factor authentication is turned on', function (): void {
    confirmTwoFactor($this->ana);

    Notification::assertSentTo($this->ana, TwoFactorChanged::class, fn (TwoFactorChanged $notice): bool => $notice->isOn);
});

it('emails the user when a superuser resets their two-factor authentication', function (): void {
    $bruno = noticeUser('bruno@example.com', 'editor');
    confirmTwoFactor($bruno);
    $this->actingAs($this->ana);

    lynx()->run(ResetTwoFactor::class, ['user' => (string) $bruno->id]);

    Notification::assertSentTo($bruno, TwoFactorChanged::class, fn (TwoFactorChanged $notice): bool => ! $notice->isOn);
});

it('says what changed in the email', function (): void {
    confirmTwoFactor($this->ana);

    Notification::assertSentTo($this->ana, TwoFactorChanged::class, function (TwoFactorChanged $notice): bool {
        $mail = $notice->toMail($this->ana);

        return str_contains((string) $mail->subject, 'Two-factor authentication was turned on')
            && str_contains(implode(' ', $mail->introLines), 'a code from your authentication app');
    });
});

it('sends nothing on a site that sends no email', function (): void {
    config(['lynx-admin.auth.mail' => false]);

    confirmTwoFactor($this->ana);

    Notification::assertNothingSent();
});

it('sends nothing to a user who cannot open the admin', function (): void {
    $member = noticeUser('mia@example.com', null);

    confirmTwoFactor($member);

    Notification::assertNothingSent();
});

it('shows superusers who has not set up two-factor authentication', function (): void {
    confirmTwoFactor($this->ana);
    noticeUser('bruno@example.com', 'editor');
    noticeUser('mia@example.com', null);
    $this->actingAs($this->ana->fresh());

    $screen = Livewire::test(UserRoles::class);

    expect(missingTwoFactor($screen->html()))->toBe([(string) User::query()->where('email', 'bruno@example.com')->value('id')]);
});

it('shows nobody as missing two-factor authentication when the site does not require it', function (): void {
    config(['lynx-admin.auth.require_two_factor' => false]);
    noticeUser('bruno@example.com', 'editor');
    $this->actingAs($this->ana);

    expect(missingTwoFactor(Livewire::test(UserRoles::class)->html()))->toBe([]);
});
