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
final readonly class PermissionHolders
{
    public function __construct(private Superusers $superusers) {}

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

    /** Whether any user holds a superuser role and still has an account; a site without one has no one who can manage roles. */
    public function hasSuperuser(): bool
    {
        return $this->superusers->exist();
    }
}
