<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Auth\AdminAuth;
use LynxCms\Admin\Livewire\Concerns\InvitesUsers;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Admin\Livewire\Concerns\RequiresSuperuser;
use LynxCms\Admin\Users\LynxUsers;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleAssignment;
use LynxCms\Core\Support\Value;

/**
 * Setup > Users: find the host app's users (LynxUsers), set their roles through roles.assign
 * (ADR-0033) and invite new ones (ADR-0041). Superusers only.
 *
 * @api
 */
final class UserRoles extends Component
{
    use InvitesUsers;
    use Notifies;
    use RequiresSuperuser;

    private const int PER_PAGE = 50;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var array<string, list<string>> user id => role names, for the users on screen */
    public array $roles = [];

    #[Locked]
    public ?string $status = null;

    /** Runs on mount and on every Livewire update, so the checks also guard /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        $this->authorizeSuperuser();
    }

    public function saveRoles(string $user): void
    {
        $this->status = null;
        lynx()->run(AssignRoles::class, ['user' => $user, 'roles' => Value::strings($this->roles[$user] ?? [])]);
        $this->notify('Saved.');
    }

    public function render(LynxUsers $users): View
    {
        $found = $users->search($this->search, self::PER_PAGE);
        $this->loadRoles(array_column($found, 'id'));

        return view('lynx-admin::livewire.user-roles', [
            'users' => $found,
            'roleNames' => Role::query()->orderBy('name')->pluck('label', 'name')->all(),
            'canInvite' => AdminAuth::isEnabled(),
            'pending' => AdminAuth::isEnabled() ? $this->pendingInvitations(array_column($found, 'id')) : [],
        ]);
    }

    /**
     * Stored roles for users on screen the form does not hold yet; edits in progress stay.
     *
     * @param  list<string>  $ids
     */
    private function loadRoles(array $ids): void
    {
        $missing = array_values(array_diff($ids, array_map(strval(...), array_keys($this->roles))));

        foreach ($missing as $id) {
            $this->roles[$id] = [];
        }

        $names = Role::query()->pluck('name', 'id')->all();

        foreach (RoleAssignment::query()->whereIn('user_id', $missing)->get() as $assignment) {
            $this->roles[$assignment->user_id][] = Value::string($names[$assignment->role_id] ?? '');
        }
    }
}
