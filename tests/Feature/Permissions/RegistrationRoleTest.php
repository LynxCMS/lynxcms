<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\GenericUser;
use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;

/*
 * People who sign up on the site get no role from Lynx (ADR-0127): a user without a role has no
 * rights, so they see the website like any visitor and never the admin. An app that wants to give
 * sign-ups a role does it in its own registration code.
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

it('gives no role on sign-up whatever the configuration says', function (): void {
    installLynx();
    lynx()->run(SaveRole::class, ['name' => 'reviewer', 'superuser' => true]);
    config(['lynx.permissions.registration_role' => 'reviewer']);

    registerVisitor(50);

    expect(RoleAssignment::query()->where('user_id', '50')->exists())->toBeFalse();
});
