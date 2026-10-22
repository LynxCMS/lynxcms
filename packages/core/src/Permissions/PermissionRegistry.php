<?php

declare(strict_types=1);

namespace LynxCms\Core\Permissions;

/**
 * The permissions roles can grant (ADR-0033). Core registers its own; modules add theirs:
 * lynx()->permissions()->register('menus.manage', 'Manage menus').
 *
 * @api
 */
final class PermissionRegistry
{
    /** Core permissions: name => [label, whether grants can be limited to templates and branches]. */
    public const array CORE = [
        'admin.access' => ['Open the admin', false],
        'pages.view' => ['See pages and unpublished content in the admin and agent read tools', true],
        'pages.edit' => ['Edit existing pages, translate them, delete old URLs', true],
        'pages.create' => ['Create pages', true],
        'pages.publish' => ['Publish and unpublish pages', true],
        'pages.status' => ['Hide, unhide, lock and unlock pages', true],
        'pages.move' => ['Move and sort pages', true],
        'pages.delete' => ['Delete pages', true],
        'pages.template' => ['Switch the template of pages', true],
        'schema.manage' => ['Manage fields, templates and languages', false],
        'agent.use' => ['Use the built-in agent and approve its plans', false],
        'system.manage' => ['Change system pages (for example when uninstalling a module)', false],
    ];

    /** @var array<string, array{label: string, scoped: bool}> */
    private array $permissions = [];

    /** @var array<string, list<string>> permission => permissions a role granting it must grant too */
    private array $requires = [];

    /** @var array<string, string> branch root template => permission that covers its pages */
    private array $delegated = [];

    public function __construct()
    {
        foreach (self::CORE as $name => [$label, $scoped]) {
            $this->register($name, $label, $scoped);
        }
    }

    /**
     * $scoped: whether grants of it can be limited to templates and branches of the tree.
     * $requires: permissions a role granting it must also grant, in any scope (menus.manage
     * needs pages.view, since managing menus means finding the pages to link).
     *
     * @param  list<string>  $requires
     */
    public function register(string $name, string $label, bool $scoped = false, array $requires = []): void
    {
        $this->permissions[$name] = ['label' => $label, 'scoped' => $scoped];
        $this->requires[$name] = $requires;
    }

    /**
     * @return list<string>
     */
    public function requires(string $name): array
    {
        return $this->requires[$name] ?? [];
    }

    /**
     * Let a module's permission cover every pages.* check inside its own branch: pages below (and
     * including) a page using $rootTemplate, e.g. delegate('menus', 'menus.manage').
     */
    public function delegate(string $rootTemplate, string $permission): void
    {
        $this->delegated[$rootTemplate] = $permission;
    }

    /**
     * @return array<string, string> branch root template => permission
     */
    public function delegations(): array
    {
        return $this->delegated;
    }

    public function has(string $name): bool
    {
        return isset($this->permissions[$name]);
    }

    public function isScoped(string $name): bool
    {
        return $this->permissions[$name]['scoped'] ?? false;
    }

    /**
     * @return array<string, array{label: string, scoped: bool}>
     */
    public function all(): array
    {
        return $this->permissions;
    }
}
