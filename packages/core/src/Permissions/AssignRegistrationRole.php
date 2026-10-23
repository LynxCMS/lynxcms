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
 * which grants nothing), so a sign-up never has the full rights that signed in users keep while
 * no one holds a role (ADR-0033, ADR-0073).
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
            Log::warning("Lynx gave user {$user} no role on sign-up: there is no role [{$role}]. Run php artisan lynx:install, or set lynx.permissions.registration_role to a role that exists.");

            return;
        }

        $hadSuperuser = $this->hasSuperuser();
        $this->runner->actingAs(Actor::system(), fn (): mixed => $this->runner->run(AssignRoles::class, ['user' => $user, 'roles' => [$role]]));

        if (! $hadSuperuser) {
            Log::warning("Lynx now enforces roles: user {$user} signed up and got the [{$role}] role, and no user is a superuser yet. Run php artisan lynx:install --user=ID to make one.");
        }
    }

    private function hasSuperuser(): bool
    {
        return RoleAssignment::query()->whereIn('role_id', Role::query()->where('is_superuser', true)->select('id'))->exists();
    }
}
