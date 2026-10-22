<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Admin\Livewire\Concerns\RequiresSuperuser;
use LynxCms\Core\Actions\Roles\DeleteRole;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Permissions\RoleGrant;
use LynxCms\Core\Support\Value;

/**
 * Setup > Roles > New role and Edit role: label, superuser, and a grid of permissions, each
 * optionally limited to templates and branches (ADR-0033). Writes go through roles.save and
 * roles.delete. Superusers only.
 *
 * Permission names hold dots, which Livewire reads as nesting, so the grid keys them with "__".
 *
 * @api
 */
final class RoleForm extends Component
{
    use Notifies;
    use RequiresSuperuser;

    #[Locked]
    public ?string $role = null;

    public string $name = '';

    public string $label = '';

    public bool $superuser = false;

    /** @var array<string, array{on: bool, templates: list<string>, branches: list<int>}> grid key => grant */
    public array $grants = [];

    #[Locked]
    public ?string $status = null;

    #[Locked]
    public bool $isConfirmingDelete = false;

    #[Locked]
    public int $deleteUsers = 0;

    /** Runs on mount and on every Livewire update, so the checks also guard /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        $this->authorizeSuperuser();
    }

    public function mount(?string $role = null): void
    {
        foreach (array_keys(lynx()->permissions()->all()) as $permission) {
            $this->grants[$this->key($permission)] = ['on' => false, 'templates' => [], 'branches' => []];
        }

        $existing = $role === null ? null : Role::query()->where('name', $role)->with('grants')->first();

        if ($existing instanceof Role) {
            $this->role = $existing->name;
            $this->name = $existing->name;
            $this->label = $existing->label;
            $this->superuser = $existing->is_superuser;
            $existing->grants->each(function (RoleGrant $grant): void {
                $this->grants[$this->key($grant->permission)] = ['on' => true, 'templates' => $grant->templates ?? [], 'branches' => $grant->branches ?? []];
            });
        }
    }

    public function save(): void
    {
        $this->status = null;
        lynx()->run(SaveRole::class, [
            'name' => $this->role ?? $this->name,
            'label' => trim($this->label) !== '' ? trim($this->label) : null,
            'superuser' => $this->superuser,
            'grants' => $this->grantsInput(),
        ]);

        if ($this->role === null) {
            $this->redirectRoute('lynx.admin.roles.edit', $this->name);

            return;
        }

        $this->notify('Saved.');
    }

    public function confirmDelete(): void
    {
        $this->deleteUsers = Role::query()->where('name', $this->role)->first()?->assignments()->count() ?? 0;
        $this->isConfirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->isConfirmingDelete = false;
    }

    public function delete(): void
    {
        lynx()->run(DeleteRole::class, ['role' => (string) $this->role]);
        $this->redirectRoute('lynx.admin.roles');
    }

    public function render(): View
    {
        $permissions = [];

        foreach (lynx()->permissions()->all() as $permission => $info) {
            $permissions[$this->key($permission)] = ['name' => $permission] + $info;
        }

        return view('lynx-admin::livewire.role-form', [
            'permissions' => $permissions,
            'templates' => lynx()->templates->all()->map(fn (Template $template): string => (string) $template->name)->sort()->values()->all(),
        ]);
    }

    private function key(string $permission): string
    {
        return str_replace('.', '__', $permission);
    }

    /**
     * @return list<array{permission: string, templates?: list<string>, branches?: list<int>}>
     */
    private function grantsInput(): array
    {
        $input = [];

        foreach (lynx()->permissions()->all() as $permission => $info) {
            $grant = Value::map($this->grants[$this->key($permission)] ?? []);

            if (! (bool) ($grant['on'] ?? false)) {
                continue;
            }

            $input[] = ['permission' => $permission] + ($info['scoped'] ? array_filter([
                'templates' => Value::strings($grant['templates'] ?? []),
                'branches' => Value::ints($grant['branches'] ?? []),
            ]) : []);
        }

        return $input;
    }
}
