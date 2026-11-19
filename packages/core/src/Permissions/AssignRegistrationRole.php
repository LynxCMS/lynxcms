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
 * app sets one (ADR-0073, ADR-0127). Without it they get no role, and so no rights.
 *
 * @internal
 */
final readonly class AssignRegistrationRole
{
    public function __construct(private ActionRunner $runner) {}

    public function handle(Registered $event): void
    {
        $role = Value::string(config('lynx.permissions.registration_role'));
        $user = Value::string($event->user->getAuthIdentifier());

        if ($role === '' || $user === '' || RoleAssignment::query()->where('user_id', $user)->exists()) {
            return;
        }

        if (! Role::query()->where('name', $role)->exists()) {
            Log::warning("Lynx gave user {$user} no role on sign-up: there is no role [{$role}]. Create the role, or set lynx.permissions.registration_role to a role that exists.");

            return;
        }

        $this->runner->actingAs(Actor::system(), fn (): mixed => $this->runner->run(AssignRoles::class, ['user' => $user, 'roles' => [$role]]));
    }
}
