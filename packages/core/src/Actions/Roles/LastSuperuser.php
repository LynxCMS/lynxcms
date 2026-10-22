<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Roles;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;

/**
 * Role changes never remove the last superuser assignment: nobody could manage roles any more,
 * or, with no assignments left, every signed in user would have full rights again (ADR-0033).
 *
 * @internal
 */
final class LastSuperuser
{
    /**
     * @param  list<int>  $removedAssignmentIds  assignments the change would remove
     */
    public static function assertKept(array $removedAssignmentIds, string $key): void
    {
        $superuserRoles = Role::query()->where('is_superuser', true)->pluck('id')->all();
        $superusers = RoleAssignment::query()->whereIn('role_id', $superuserRoles);
        $total = (clone $superusers)->count();
        $removed = $superusers->whereKey($removedAssignmentIds)->count();

        if ($total > 0 && $removed >= $total) {
            throw ValidationException::withMessages([$key => 'This would remove the last superuser. Give another user a superuser role first.']);
        }
    }
}
