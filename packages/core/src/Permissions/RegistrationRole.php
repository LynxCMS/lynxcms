<?php

declare(strict_types=1);

namespace LynxCms\Core\Permissions;

use LynxCms\Core\Support\Value;

/**
 * The role people get when they sign up on the site, from lynx.permissions.registration_role
 * (ADR-0073, ADR-0127). It may never open the admin: anyone can sign up, so such a role would let
 * anyone edit the site.
 *
 * @internal
 */
final class RegistrationRole
{
    private const string ADMIN_ACCESS = 'admin.access';

    /** The configured role name; empty when sign-ups get no role. */
    public static function name(): string
    {
        return Value::string(config('lynx.permissions.registration_role'));
    }

    public static function find(): ?Role
    {
        return Role::query()->where('name', self::name())->first();
    }

    /** Whether the role is a superuser or grants admin.access. */
    public static function opensAdmin(Role $role): bool
    {
        return $role->is_superuser || $role->grants()->where('permission', self::ADMIN_ACCESS)->exists();
    }
}
