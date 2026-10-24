<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LynxCms\Admin\Auth\Invitation;
use LynxCms\Admin\Auth\InvitationNotification;
use LynxCms\Admin\Auth\InviteUser;
use LynxCms\Admin\Auth\ResendInvitation;
use LynxCms\Admin\Livewire\UserRoles;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Permissions\RoleAssignment;
use Workbench\App\Models\User;

beforeEach(function (): void {
    courseSite();
    Notification::fake();
    $this->admin = User::query()->forceCreate(['name' => 'Ada Admin', 'email' => 'ada@example.com', 'password' => 'secret password']);
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'admin.access'], ['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => (string) $this->admin->id, 'roles' => ['superuser']]);
    $this->actingAs($this->admin);
});

/** The link in the invitation sent to an address. */
function invitationLink(string $email): string
{
    $link = null;
    Notification::assertSentOnDemand(InvitationNotification::class, function (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$link): bool {
        $link = $notification->url;

        return $notifiable->routes['mail'] === $email;
    });

    return (string) $link;
}

function invite(array $input = []): User
{
    lynx()->run(InviteUser::class, $input + ['name' => 'Bruno Costa', 'email' => 'bruno@example.com', 'roles' => ['editor']]);

    return User::query()->where('email', 'bruno@example.com')->firstOrFail();
}

it('creates the user with their roles and emails a link to choose a password', function (): void {
    $bruno = invite();

    expect(RoleAssignment::query()->where('user_id', (string) $bruno->id)->count())->toBe(1)
        ->and(invitationLink('bruno@example.com'))->toContain('/lynx/invitation/')->toContain('email=bruno%40example.com');
});

it('lets the invited user choose a password and signs them in', function (): void {
    invite();
    $link = invitationLink('bruno@example.com');
    Auth::logout();

    $this->get($link)->assertOk()->assertSee('Welcome, Bruno Costa')->assertSee('Choose a password');
    $token = (string) str($link)->after('/lynx/invitation/')->before('?');
    $this->post('/lynx/invitation', ['token' => $token, 'email' => 'bruno@example.com', 'password' => 'a long new password', 'password_confirmation' => 'a long new password'])
        ->assertRedirect('/lynx/pages');

    expect(Auth::user()?->getAuthIdentifier())->toBe(User::query()->where('email', 'bruno@example.com')->value('id'));
});

it('names no one for an invitation link without a valid token', function (): void {
    invite();
    Auth::logout();

    $this->get('/lynx/invitation/made-up-token?email=ada%40example.com')->assertOk()->assertDontSee('Ada Admin');
    $this->get('/lynx/invitation/made-up-token?email=bruno%40example.com')->assertOk()->assertDontSee('Bruno Costa');
});

it('keeps the link valid for days, not the hour of a password reset', function (): void {
    invite();
    $token = (string) str(invitationLink('bruno@example.com'))->after('/lynx/invitation/')->before('?');
    Auth::logout();
    $this->travel(3)->days();

    $this->post('/lynx/invitation', ['token' => $token, 'email' => 'bruno@example.com', 'password' => 'a long new password', 'password_confirmation' => 'a long new password'])
        ->assertRedirect('/lynx/pages');
});

it('refuses an expired invitation', function (): void {
    invite();
    $token = (string) str(invitationLink('bruno@example.com'))->after('/lynx/invitation/')->before('?');
    Auth::logout();
    $this->travel(8)->days();

    $this->post('/lynx/invitation', ['token' => $token, 'email' => 'bruno@example.com', 'password' => 'a long new password', 'password_confirmation' => 'a long new password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('refuses an email that already has an account', function (): void {
    invite(['email' => 'ada@example.com']);
})->throws(ValidationException::class);

it('refuses an invalid email', function (): void {
    invite(['email' => 'not an email']);
})->throws(ValidationException::class);

it('creates nothing and sends nothing on a dry run', function (): void {
    lynx()->run(InviteUser::class, ['name' => 'Bruno Costa', 'email' => 'bruno@example.com'], dryRun: true);

    expect(User::query()->where('email', 'bruno@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('lets only superusers invite', function (): void {
    $eva = User::query()->forceCreate(['name' => 'Eva', 'email' => 'eva@example.com', 'password' => 'secret password']);
    lynx()->run(AssignRoles::class, ['user' => (string) $eva->id, 'roles' => ['editor']]);
    $this->actingAs($eva);

    invite();
})->throws(AuthorizationException::class);

it('is not an agent tool', function (): void {
    expect(InviteUser::exposedAsTool())->toBeFalse()
        ->and(app(ActionRegistry::class)->find('users.invite'))->toBe(InviteUser::class);
});

it('invites from the Users screen', function (): void {
    Livewire::test(UserRoles::class)
        ->assertSee('Invite user')
        ->set('invite.name', 'Bruno Costa')->set('invite.email', 'bruno@example.com')->set('invite.roles', ['editor'])
        ->call('sendInvitation')
        ->assertHasNoErrors()
        ->assertSet('status', 'Invitation sent to bruno@example.com.')
        ->assertSet('invite.email', '');

    invitationLink('bruno@example.com');
});

it('shows invitation errors by their inputs', function (): void {
    Livewire::test(UserRoles::class)->set('invite.email', 'not an email')->call('sendInvitation')->assertHasErrors(['invite.name', 'invite.email']);
    Livewire::test(UserRoles::class)->set('invite.name', 'Ada')->set('invite.email', 'ada@example.com')->call('sendInvitation')->assertHasErrors(['invite.email']);
});

it('marks invited users as pending until they choose a password', function (): void {
    $bruno = invite();
    Livewire::test(UserRoles::class)->assertSee('Invitation pending')->assertSee('Resend invitation');

    $token = (string) str(invitationLink('bruno@example.com'))->after('/lynx/invitation/')->before('?');
    Auth::logout();
    $this->post('/lynx/invitation', ['token' => $token, 'email' => 'bruno@example.com', 'password' => 'a long new password', 'password_confirmation' => 'a long new password']);

    expect(Invitation::query()->where('user_id', (string) $bruno->id)->exists())->toBeFalse();
});

it('resends an invitation with a new link', function (): void {
    $bruno = invite();
    $first = invitationLink('bruno@example.com');
    Notification::fake();

    Livewire::test(UserRoles::class)
        ->call('resendInvitation', (string) $bruno->id)
        ->assertSet('status', 'Invitation sent again to bruno@example.com.');

    $second = invitationLink('bruno@example.com');
    expect($second)->not->toBe($first);

    Auth::logout();
    $old = (string) str($first)->after('/lynx/invitation/')->before('?');
    $this->post('/lynx/invitation', ['token' => $old, 'email' => 'bruno@example.com', 'password' => 'a long new password', 'password_confirmation' => 'a long new password'])
        ->assertSessionHasErrors('email');
});

it('only resends pending invitations', function (): void {
    lynx()->run(ResendInvitation::class, ['user' => (string) $this->admin->id]);
})->throws(ValidationException::class);

it('stops being pending when the user resets their password instead', function (): void {
    $bruno = invite();
    Auth::logout();
    $token = app('auth.password.broker')->createToken($bruno);
    $this->post('/lynx/reset-password', ['token' => $token, 'email' => 'bruno@example.com', 'password' => 'a long new password', 'password_confirmation' => 'a long new password']);

    expect(Invitation::query()->where('user_id', (string) $bruno->id)->exists())->toBeFalse();
});

it('lets only superusers resend invitations', function (): void {
    $bruno = invite();
    $eva = User::query()->forceCreate(['name' => 'Eva', 'email' => 'eva@example.com', 'password' => 'secret password']);
    lynx()->run(AssignRoles::class, ['user' => (string) $eva->id, 'roles' => ['editor']]);
    $this->actingAs($eva);

    lynx()->run(ResendInvitation::class, ['user' => (string) $bruno->id]);
})->throws(AuthorizationException::class);
