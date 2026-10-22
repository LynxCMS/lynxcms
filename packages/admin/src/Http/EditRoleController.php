<?php

declare(strict_types=1);

namespace LynxCms\Admin\Http;

use Illuminate\Contracts\View\View;
use LynxCms\Admin\Livewire\RoleForm;
use LynxCms\Core\Permissions\Role;

/**
 * The New role and Edit role screens. A controller rather than a closure so route:cache keeps working.
 *
 * @internal
 */
final class EditRoleController
{
    public function __invoke(?string $role = null): View
    {
        abort_unless(RoleForm::currentUserIsSuperuser(), 403);
        abort_unless($role === null || Role::query()->where('name', $role)->exists(), 404);

        return view('lynx-admin::roles.edit', ['role' => $role]);
    }
}
