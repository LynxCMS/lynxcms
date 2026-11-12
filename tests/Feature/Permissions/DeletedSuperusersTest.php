<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Permissions\PermissionHolders;
use LynxCms\Core\Permissions\RoleAssignment;
use Workbench\App\Models\User;

/*
 * Lynx keeps user ids without a foreign key, so deleting a user leaves their roles behind. A
 * superuser whose account is gone can't manage anything: the last superuser check and the "is
 * there a superuser" question count only the ones whose account still exists (ADR-0114).
 */

function superuserAccount(string $name): string
{
    $id = userAccount($name);
    lynx()->run(AssignRoles::class, ['user' => $id, 'roles' => ['superuser']]);

    return $id;
}

beforeEach(function (): void {
    installLynx();
    $this->ana = superuserAccount('Ana');
    $this->bea = superuserAccount('Bea');
    User::query()->whereKey($this->bea)->delete();
});

it('refuses to remove the last superuser whose account still exists', function (): void {
    expect(fn (): ActionResult => lynx()->run(AssignRoles::class, ['user' => $this->ana, 'roles' => []]))->toThrow(ValidationException::class, 'last superuser');
});

it('removes the superuser role of a deleted account', function (): void {
    lynx()->run(AssignRoles::class, ['user' => $this->bea, 'roles' => []]);

    expect(RoleAssignment::query()->where('user_id', $this->bea)->exists())->toBeFalse();
});

it('says there is no superuser once every superuser account is gone', function (): void {
    expect(app(PermissionHolders::class)->hasSuperuser())->toBeTrue();

    User::query()->whereKey($this->ana)->delete();

    expect(app(PermissionHolders::class)->hasSuperuser())->toBeFalse();
});

it('counts an id that can not be a key of the users table as a deleted account', function (): void {
    lynx()->run(AssignRoles::class, ['user' => 'sso|1234', 'roles' => ['superuser']]);

    expect(fn (): ActionResult => lynx()->run(AssignRoles::class, ['user' => $this->ana, 'roles' => []]))->toThrow(ValidationException::class, 'last superuser');
});
