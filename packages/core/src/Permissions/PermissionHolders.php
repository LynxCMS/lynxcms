<?php

declare(strict_types=1);

namespace LynxCms\Core\Permissions;

use LynxCms\Core\Support\Value;

/**
 * The users who hold a permission anywhere: through a role granting it, or a superuser role. For
 * modules that tell people about work waiting for them, such as new form submissions.
 *
 * @api
 */
final class PermissionHolders
{
    /**
     * @return list<string> user ids, as role assignments store them
     */
    public function userIds(string $permission): array
    {
        $roles = Role::query()
            ->where('is_superuser', true)
            ->orWhereHas('grants', fn ($grants) => $grants->where('permission', $permission))
            ->pluck('id');

        return array_values(array_unique(array_map(Value::string(...), RoleAssignment::query()->whereIn('role_id', $roles)->pluck('user_id')->all())));
    }

    /** Whether any user holds a superuser role; a site without one has no one who can open the admin. */
    public function hasSuperuser(): bool
    {
        return RoleAssignment::query()->whereIn('role_id', Role::query()->where('is_superuser', true)->select('id'))->exists();
    }
}
