<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Log;
use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;

/*
 * People who sign up on the site get the "guest" role (ADR-0073): they see the website like any
 * visitor and never the admin. Without it, the first sign-up on a site where nobody holds a role
 * yet would have every right (ADR-0033).
 */

function registerVisitor(int $id): void
{
    event(new Registered(new GenericUser(['id' => $id])));
}

it('creates a guest role with no grants on install', function (): void {
    installLynx();
    lynx()->run(InstallLynx::class);

    $guest = Role::query()->where('name', 'guest')->sole();

    expect($guest->is_superuser)->toBeFalse()
        ->and($guest->grants()->count())->toBe(0)
        ->and(Role::query()->where('name', 'guest')->count())->toBe(1);
});

it('gives a new sign-up the guest role, recorded as the system', function (): void {
    installLynx();

    registerVisitor(50);

    expect(RoleAssignment::query()->where('user_id', '50')->sole()->role_id)->toBe(Role::query()->where('name', 'guest')->value('id'))
        ->and(ActivityEvent::query()->where('action', 'roles.assign')->latest('id')->first()?->actor_kind)->toBe('system');
});

it('keeps a guest out of the admin while the site stays open to them', function (): void {
    installLynx();
    registerVisitor(50);

    $this->actingAs(new GenericUser(['id' => 50]));

    $this->get('/lynx/pages')->assertForbidden();
    $this->get('/')->assertOk();
});

it('switches role enforcement on with the first sign-up, and says how to get back in', function (): void {
    installLynx();
    Log::spy();
    expect(app(Authorizer::class)->isEnforced())->toBeFalse();

    registerVisitor(50);

    expect(app(Authorizer::class)->isEnforced())->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'lynx:install --user='))->once();
});

it('does not warn when a superuser exists', function (): void {
    installLynx();
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    Log::spy();

    registerVisitor(50);

    Log::shouldNotHaveReceived('warning');
});

it('leaves users who already hold roles as they are', function (): void {
    installLynx();
    lynx()->run(AssignRoles::class, ['user' => '50', 'roles' => ['editor']]);

    registerVisitor(50);

    expect(RoleAssignment::query()->where('user_id', '50')->count())->toBe(1)
        ->and(Role::query()->whereKey(RoleAssignment::query()->where('user_id', '50')->value('role_id'))->value('name'))->toBe('editor');
});

it('assigns nothing when the registration role is turned off', function (): void {
    installLynx();
    config(['lynx.permissions.registration_role' => null]);

    registerVisitor(50);

    expect(RoleAssignment::query()->count())->toBe(0);
});

it('assigns nothing and logs why when the role does not exist', function (): void {
    installLynx();
    Role::query()->where('name', 'guest')->delete();
    Log::spy();

    registerVisitor(50);

    expect(RoleAssignment::query()->count())->toBe(0);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, '[guest]'))->once();
});
