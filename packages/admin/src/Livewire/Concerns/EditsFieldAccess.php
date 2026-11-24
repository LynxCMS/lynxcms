<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use LynxCms\Core\Permissions\Role;
use LynxCms\Core\Support\Value;

/**
 * The field form's Access tab (ADR-0151): whether the field manages its access, and the roles
 * that may see it and edit it. Saved as the field's "access" setting, or null when not managed.
 *
 * @internal
 */
trait EditsFieldAccess
{
    /** Whether only the roles ticked below see and edit the field. */
    public bool $manageAccess = false;

    /** @var list<string> roles that may see the field, when access is managed */
    public array $viewRoles = [];

    /** @var list<string> roles that may edit (and so see) the field, when access is managed */
    public array $editRoles = [];

    /** @param  array<string, mixed>  $config  the field's settings */
    protected function loadAccess(array $config): void
    {
        $access = Value::map($config['access'] ?? []);
        $this->manageAccess = is_array($config['access'] ?? null);
        $this->viewRoles = Value::strings($access['view'] ?? []);
        $this->editRoles = Value::strings($access['edit'] ?? []);
    }

    /**
     * The "access" setting to save; null stops managing it.
     *
     * @return array{view: list<string>, edit: list<string>}|null
     */
    protected function accessSetting(): ?array
    {
        return $this->manageAccess ? ['view' => array_values($this->viewRoles), 'edit' => array_values($this->editRoles)] : null;
    }

    /**
     * Roles the tab lists. Superusers pass every check, so they are left out; a role without
     * pages.edit can't edit the field, so its Edit box is off.
     *
     * @return list<array{name: string, label: string, canEditPages: bool}>
     */
    protected function accessRoles(): array
    {
        return array_values(Role::query()->where('is_superuser', false)->with('grants')->orderBy('label')->get()
            ->map(fn (Role $role): array => [
                'name' => (string) $role->name,
                'label' => (string) $role->label ?: (string) $role->name,
                'canEditPages' => $role->grants->contains('permission', 'pages.edit'),
            ])->all());
    }
}
