<?php

declare(strict_types=1);

namespace LynxCms\Core\Permissions;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Support\Value;

/**
 * Gives people who sign up on the site the role in lynx.permissions.registration_role, when the
 * app sets one (ADR-0073, ADR-0127). Without it they get no role, and so no rights. A role that
 * opens the admin is never given this way.
 *
 * @internal
 */
final readonly class AssignRegistrationRole
{
    public function __construct(private ActionRunner $runner) {}

    public function handle(Registered $event): void
    {
        $name = RegistrationRole::name();
        $user = Value::string($event->user->getAuthIdentifier());

        if ($name === '' || $user === '' || RoleAssignment::query()->where('user_id', $user)->exists()) {
            return;
        }

        $role = $this->usableRole($name, $user);

        if ($role instanceof Role) {
            $this->runner->actingAs(Actor::system(), fn (): mixed => $this->runner->run(AssignRoles::class, ['user' => $user, 'roles' => [$name]]));
        }
    }

    private function usableRole(string $name, string $user): ?Role
    {
        $role = RegistrationRole::find();

        if (! $role instanceof Role) {
            Log::warning("Lynx gave user {$user} no role on sign-up: there is no role [{$name}]. Create the role, or set lynx.permissions.registration_role to a role that exists.");

            return null;
        }

        if (RegistrationRole::opensAdmin($role)) {
            Log::error("Lynx gave user {$user} no role on sign-up: the role [{$name}] opens the admin, and anyone can sign up. Remove admin.access and superuser from it, or name another role in lynx.permissions.registration_role.");

            return null;
        }

        return $role;
    }
}
