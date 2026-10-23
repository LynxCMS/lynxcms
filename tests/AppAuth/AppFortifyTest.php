<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use LynxCms\Admin\Auth\AdminAuth;
use LynxCms\Admin\Auth\InvitationNotification;
use LynxCms\Admin\Livewire\UserRoles;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;
use LynxCms\Tests\Fixtures\AppAuth\CreateNewUser;
use Workbench\App\Models\User;

/*
 * A site whose app signs everyone in with its own Fortify, such as one with a members area
 * (ADR-0074): staff and members share the app's /login, roles decide who opens the admin, and
 * Lynx keeps its invitations and account screen.
 */

beforeEach(function (): void {
    installLynx();
    Fortify::createUsersUsing(CreateNewUser::class);
    Fortify::loginView(fn (): string => 'App sign-in');
    Fortify::registerView(fn (): string => 'App sign-up');
    $this->owner = User::query()->forceCreate(['name' => 'Olga Owner', 'email' => 'olga@example.com', 'password' => Hash::make('correct horse')]);
    lynx()->run(AssignRoles::class, ['user' => (string) $this->owner->id, 'roles' => ['superuser']]);
});

it('uses the app\'s sign-in instead of its own', function (): void {
    expect(AdminAuth::mode())->toBe('app');

    $this->get('/lynx/pages')->assertRedirect('/login');
    $this->get('/lynx/login')->assertNotFound();
    $this->get('/login')->assertOk()->assertSee('App sign-in');
});

it('lets staff sign in at the app\'s /login and open the admin', function (): void {
    $this->post('/login', ['email' => 'olga@example.com', 'password' => 'correct horse'])->assertRedirect('/account');

    $this->get('/lynx/pages')->assertOk();
});

it('gives people who sign up the guest role, and keeps them out of the admin', function (): void {
    $this->post('/register', ['name' => 'Mia Member', 'email' => 'mia@example.com', 'password' => 'long enough', 'password_confirmation' => 'long enough'])
        ->assertRedirect('/account');

    $member = User::query()->where('email', 'mia@example.com')->sole();
    expect(RoleAssignment::query()->where('user_id', (string) $member->id)->value('role_id'))->toBe(Role::query()->where('name', 'guest')->value('id'));

    $this->get('/lynx/pages')->assertForbidden();
    $this->get('/')->assertOk();
});

it('still invites staff, who choose a password and land in the admin', function (): void {
    Notification::fake();
    $this->actingAs($this->owner);

    Livewire::test(UserRoles::class)->assertSee('Send invitation')
        ->set('invite.name', 'Eva Editor')->set('invite.email', 'eva@example.com')->set('invite.roles', ['editor'])
        ->call('sendInvitation')->assertHasNoErrors();

    $link = null;
    Notification::assertSentOnDemand(InvitationNotification::class, function (InvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$link): bool {
        $link = $notification->url;

        return $notifiable->routes['mail'] === 'eva@example.com';
    });

    auth()->logout();
    $this->get((string) $link)->assertOk();
});

it('keeps the account screen, with two-factor authentication when the app turns it on', function (): void {
    $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get('/lynx/account/security')->assertOk()->assertSee('Two-factor authentication');
});

it('leaves two-factor authentication off the account screen when the app does not offer it', function (): void {
    config(['fortify.features' => [Features::registration()]]);
    $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get('/lynx/account/security')->assertOk()->assertDontSee('Two-factor authentication');
});
