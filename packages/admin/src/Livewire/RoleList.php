<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Livewire\Concerns\RequiresSuperuser;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Support\Value;

/**
 * Setup > Roles: every role with its grants and users (ADR-0033). Superusers only.
 *
 * @api
 */
final class RoleList extends Component
{
    use RequiresSuperuser;

    /** Runs on mount and on every Livewire update, so the checks also guard /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        $this->authorizeSuperuser();
    }

    public function render(): View
    {
        $rows = [];

        foreach (Role::query()->withCount(['grants', 'assignments'])->orderBy('name')->get() as $role) {
            $rows[$role->name] = [
                'label' => $role->label,
                'superuser' => $role->is_superuser,
                'grants' => Value::int($role->getAttribute('grants_count')),
                'users' => Value::int($role->getAttribute('assignments_count')),
            ];
        }

        return view('lynx-admin::livewire.role-list', ['rows' => $rows]);
    }
}
