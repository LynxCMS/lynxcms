<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;

/*
 * roles.assign replaces a user's roles, and refuses names that are no role, keeping the roles the
 * user had.
 */

beforeEach(function (): void {
    installLynx();
    lynx()->run(SaveRole::class, ['name' => 'editor', 'grants' => [['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '7', 'roles' => ['editor']]);
});

/** @return list<string> */
function rolesOfSeven(): array
{
    $roleIds = RoleAssignment::query()->where('user_id', '7')->pluck('role_id')->all();

    return Role::query()->whereIn('id', $roleIds)->orderBy('name')->pluck('name')->all();
}

it('refuses a role that does not exist and keeps the user\'s roles', function (): void {
    expect(fn (): ActionResult => lynx()->run(AssignRoles::class, ['user' => '7', 'roles' => ['editor', 'nope']]))->toThrow(ValidationException::class)
        ->and(rolesOfSeven())->toBe(['editor']);
});

it('removes every role with an empty list', function (): void {
    lynx()->run(AssignRoles::class, ['user' => '7', 'roles' => []]);

    expect(rolesOfSeven())->toBe([]);
});
