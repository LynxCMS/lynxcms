<?php

declare(strict_types=1);

use App\Listeners\ForgetLynxRoles;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Permissions\RoleAssignment;
use Workbench\App\Models\User;

/*
 * The users page of the documentation (docs/deeper/users.md): Lynx keeps user ids in its own
 * tables without a foreign key, so an app removes a user's roles when it deletes the user.
 */

require_once __DIR__.'/../../../docs/snippets/advanced/ForgetLynxRoles.php';

beforeEach(function (): void {
    installLynx();
    $this->owner = User::query()->forceCreate(['name' => 'Olga Owner', 'email' => 'olga@example.com', 'password' => Hash::make('correct horse')]);
    $this->editor = User::query()->forceCreate(['name' => 'Eli Editor', 'email' => 'eli@example.com', 'password' => Hash::make('correct horse')]);
    lynx()->run(AssignRoles::class, ['user' => (string) $this->owner->getKey(), 'roles' => ['superuser']]);
    lynx()->run(AssignRoles::class, ['user' => (string) $this->editor->getKey(), 'roles' => ['editor']]);
    User::deleting(new ForgetLynxRoles);
});

function holdsRoles(User $user): bool
{
    return RoleAssignment::query()->where('user_id', (string) $user->getKey())->exists();
}

it('removes the roles of a user the app deletes', function (): void {
    $this->editor->delete();

    expect(holdsRoles($this->editor))->toBeFalse()
        ->and(holdsRoles($this->owner))->toBeTrue();
});

it('keeps the last superuser, and the user with them', function (): void {
    expect(fn () => $this->owner->delete())->toThrow(ValidationException::class)
        ->and(holdsRoles($this->owner))->toBeTrue()
        ->and(User::query()->whereKey($this->owner->getKey())->exists())->toBeTrue();
});
