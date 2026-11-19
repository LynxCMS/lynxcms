<?php

declare(strict_types=1);

namespace LynxCms\Core\Doctor\Checks;

use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Permissions\RegistrationRole;
use LynxCms\Core\Permissions\Role;

/**
 * The role people get when they sign up (ADR-0127): it must exist, and it must not open the
 * admin. Sign-up skips such a role, so this finds the mistake before a visitor does.
 *
 * @internal
 */
final class RegistrationRoleCheck implements Check
{
    private const string FIX = 'Set LYNX_REGISTRATION_ROLE to a role that exists and grants neither admin.access nor superuser, or leave it empty.';

    public function key(): string
    {
        return 'registration-role';
    }

    public function label(): string
    {
        return 'The role given on sign-up does not open the admin';
    }

    public function run(): array
    {
        $name = RegistrationRole::name();

        if ($name === '') {
            return [];
        }

        $role = RegistrationRole::find();

        if (! $role instanceof Role) {
            return [new Finding(Severity::Warning, "The sign-up role [{$name}] does not exist: people who sign up get no role.", self::FIX)];
        }

        return RegistrationRole::opensAdmin($role)
            ? [new Finding(Severity::Error, "The sign-up role [{$name}] opens the admin: people who sign up get no role.", self::FIX)]
            : [];
    }
}
