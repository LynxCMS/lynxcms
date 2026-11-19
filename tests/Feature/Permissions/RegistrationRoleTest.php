<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Log;
use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Activity\ActivityEvent;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;

/*
 * People who sign up on the site get no role by default (ADR-0127): a user without a role has no
 * rights, so they see the website like any visitor and never the admin. A site can give them a
 * role of its own with lynx.permissions.registration_role (ADR-0073).
 */

beforeEach(fn () => app(Authorizer::class)->openForTests(false));

function registerVisitor(int $id): void
{
    event(new Registered(new GenericUser(['id' => $id])));
}

it('creates no guest role on install', function (): void {
    installLynx();
    lynx()->run(InstallLynx::class);

    expect(Role::query()->where('name', 'guest')->exists())->toBeFalse();
});

it('gives a new sign-up no role, and keeps them out of the admin', function (): void {
    installLynx();
    registerVisitor(50);

    expect(RoleAssignment::query()->where('user_id', '50')->exists())->toBeFalse();

    $this->actingAs(new GenericUser(['id' => 50]));
    $this->get('/lynx/pages')->assertForbidden();
    $this->get('/')->assertOk();
});

it('gives a new sign-up the role the site names, recorded as the system', function (): void {
    installLynx();
    lynx()->run(SaveRole::class, ['name' => 'reviewer', 'grants' => [['permission' => 'pages.view']]]);
    config(['lynx.permissions.registration_role' => 'reviewer']);

    registerVisitor(50);

    expect(RoleAssignment::query()->where('user_id', '50')->sole()->role_id)->toBe(Role::query()->where('name', 'reviewer')->value('id'))
        ->and(ActivityEvent::query()->where('action', 'roles.assign')->latest('id')->first()?->actor_kind)->toBe('system');
});

it('leaves users who already hold roles as they are', function (): void {
    installLynx();
    config(['lynx.permissions.registration_role' => 'superuser']);
    lynx()->run(AssignRoles::class, ['user' => '50', 'roles' => ['editor']]);

    registerVisitor(50);

    expect(RoleAssignment::query()->where('user_id', '50')->count())->toBe(1)
        ->and(Role::query()->whereKey(RoleAssignment::query()->where('user_id', '50')->value('role_id'))->value('name'))->toBe('editor');
});

it('assigns nothing and logs why when the role does not exist', function (): void {
    installLynx();
    config(['lynx.permissions.registration_role' => 'reviewer']);
    Log::spy();

    registerVisitor(50);

    expect(RoleAssignment::query()->count())->toBe(0);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, '[reviewer]'))->once();
});

it('gives no role that opens the admin, and logs why', function (array $role): void {
    installLynx();
    lynx()->run(SaveRole::class, ['name' => 'reviewer'] + $role);
    config(['lynx.permissions.registration_role' => 'reviewer']);
    Log::spy();

    registerVisitor(50);

    expect(RoleAssignment::query()->count())->toBe(0);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, '[reviewer]') && str_contains($message, 'admin'))->once();
})->with([
    'admin.access' => [['grants' => [['permission' => 'admin.access'], ['permission' => 'pages.view']]]],
    'superuser' => [['superuser' => true]],
]);
