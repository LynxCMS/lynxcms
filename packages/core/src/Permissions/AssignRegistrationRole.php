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
 * Gives people who sign up on the site the role in lynx.permissions.registration_role ("guest",
 * which grants nothing), so a sign-up can see what guests see and nothing more (ADR-0073). In open
 * mode the first one turns the role checks on, so a warning says when no one is a superuser yet.
 *
 * @internal
 */
final readonly class AssignRegistrationRole
{
    public function __construct(private ActionRunner $runner, private PermissionHolders $holders) {}

    public function handle(Registered $event): void
    {
        $role = Value::string(config('lynx.permissions.registration_role'));
        $user = Value::string($event->user->getAuthIdentifier());

        if ($role === '' || $user === '' || RoleAssignment::query()->where('user_id', $user)->exists()) {
            return;
        }

        if (! Role::query()->where('name', $role)->exists()) {
            Log::warning("Lynx gave user {$user} no role on sign-up: there is no role [{$role}]. Run php artisan lynx:install, or set lynx.permissions.registration_role to a role that exists.");

            return;
        }

        $wasOpen = Value::bool(config('lynx.permissions.open_until_roles')) && ! $this->holders->hasSuperuser();
        $this->runner->actingAs(Actor::system(), fn (): mixed => $this->runner->run(AssignRoles::class, ['user' => $user, 'roles' => [$role]]));

        if ($wasOpen) {
            Log::warning("Lynx now enforces roles: user {$user} signed up and got the [{$role}] role, and no user is a superuser yet. Run php artisan lynx:superuser, or lynx:install --user=EMAIL, to make one.");
        }
    }
}
