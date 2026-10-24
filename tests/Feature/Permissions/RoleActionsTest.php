<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\DeleteRole;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;

beforeEach(function (): void {
    courseSite();
});

it('creates a role with scoped grants', function (): void {
    $courses = lynx()->pages->get('/courses/')->id;

    $role = lynx()->run(SaveRole::class, [
        'name' => 'course-editor',
        'label' => 'Course editor',
        'grants' => [
            ['permission' => 'admin.access'],
            ['permission' => 'pages.edit', 'templates' => ['course'], 'branches' => [$courses]],
        ],
    ])->valueAs(Role::class);

    expect([$role->name, $role->label, $role->is_superuser])->toBe(['course-editor', 'Course editor', false])
        ->and($role->grants()->orderBy('permission')->pluck('permission')->all())->toBe(['admin.access', 'pages.edit'])
        ->and($role->grants()->where('permission', 'pages.edit')->first()?->branches)->toBe([$courses]);
});

it('replaces the grants of an existing role', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'pages.edit']]]);
    lynx()->run(SaveRole::class, ['name' => 'writer', 'label' => 'Writer', 'grants' => [['permission' => 'pages.create']]]);

    expect(Role::query()->where('name', 'writer')->firstOrFail()->grants()->orderBy('permission')->pluck('permission')->all())->toBe(['pages.create']);
});

it('refuses unknown permissions and scopes on permissions that cannot have one', function (array $grant): void {
    expect(fn (): ActionResult => lynx()->run(SaveRole::class, ['name' => 'odd', 'grants' => [$grant]]))->toThrow(ValidationException::class);
})->with([
    'unknown' => [['permission' => 'pages.fly']],
    'scoped schema' => [['permission' => 'schema.manage', 'templates' => ['course']]],
    'missing branch' => [['permission' => 'pages.edit', 'branches' => [99999]]],
]);

it('assigns a user\'s roles, replacing the previous ones', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'pages.edit']]]);
    lynx()->run(SaveRole::class, ['name' => 'publisher', 'grants' => [['permission' => 'pages.publish']]]);

    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['writer', 'publisher']]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['publisher']]);

    expect(RoleAssignment::query()->where('user_id', '5')->count())->toBe(1);
});

it('deletes a role and its assignments', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'writer']);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['writer']]);

    lynx()->run(DeleteRole::class, ['role' => 'writer']);

    expect(Role::query()->where('name', 'writer')->exists())->toBeFalse()->and(RoleAssignment::query()->count())->toBe(0);
});

it('lets only superusers manage roles once roles are in use', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(SaveRole::class, ['name' => 'writer', 'grants' => [['permission' => 'pages.edit']]]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => '5', 'roles' => ['writer']]);

    expect(fn (): mixed => lynx()->actingAs(Actor::user(5), fn (): ActionResult => lynx()->run(SaveRole::class, ['name' => 'sneaky', 'superuser' => true])))->toThrow(AuthorizationException::class)
        ->and(lynx()->actingAs(Actor::user(1), fn (): mixed => lynx()->run(SaveRole::class, ['name' => 'reviewer'])->value))->toBeInstanceOf(Role::class);
});

it('never removes the last superuser assignment', function (): void {
    lynx()->run(SaveRole::class, ['name' => 'superuser', 'superuser' => true]);
    lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => ['superuser']]);

    expect(fn (): ActionResult => lynx()->run(AssignRoles::class, ['user' => '1', 'roles' => []]))->toThrow(ValidationException::class, 'last superuser')
        ->and(fn (): ActionResult => lynx()->run(DeleteRole::class, ['role' => 'superuser']))->toThrow(ValidationException::class, 'last superuser');
});
