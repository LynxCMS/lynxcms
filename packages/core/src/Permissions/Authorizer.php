<?php

declare(strict_types=1);

namespace LynxCms\Core\Permissions;

use Illuminate\Auth\Access\AuthorizationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageAncestry;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Query\PageScope;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Value;

/**
 * Whether an actor holds a permission, optionally for a page or a template (ADR-0033, ADR-0078).
 *
 * A user without a role has no rights; lynx.permissions.open_until_roles brings back the old
 * "everyone may do everything until any user has a role", for local work only. Visitors who are
 * not signed in hold only what is open to everyone. The console and the system actor always have
 * every right. Users, and MCP clients and agents acting for a user, have the union of that user's
 * roles; a superuser role passes every check. MCP clients and agents without a user get the role
 * named by lynx.permissions.anonymous_role, or nothing. Grants can be limited to templates and to
 * branches of the tree.
 *
 * @api
 */
final class Authorizer implements Flushable
{
    private ?bool $isEnforced = null;

    /** @var array<string, array{superuser: bool, grants: list<RoleGrant>}|null> */
    private array $rights = [];

    public function __construct(
        private readonly PageAncestry $ancestry,
        private readonly PermissionRegistry $permissions,
        private readonly PageLoader $pages,
        private readonly ScopeRules $scopeRules,
    ) {}

    /**
     * @param  Page|null  $page  the page acted on; for a new page, its parent
     * @param  Template|null  $template  the template to check, when not the page's own (a new page's)
     */
    public function allows(Actor $actor, string $permission, ?Page $page = null, ?Template $template = null): bool
    {
        if ($this->hasEveryRight($actor)) {
            return true;
        }

        $rights = $this->rightsOf($actor);

        if ($rights === null || $rights['superuser']) {
            return $rights !== null;
        }

        $templateName = $template !== null ? (string) $template->name : ($page instanceof Page ? (string) $page->template()->name : null);

        foreach ($rights['grants'] as $grant) {
            if ($grant->permission === $permission && $this->covers($grant, $templateName, $page)) {
                return true;
            }
        }

        $delegate = str_starts_with($permission, 'pages.') ? $this->delegateFor($page) : null;

        return $delegate !== null && $this->allows($actor, $delegate);
    }

    /** Like allows(), throwing a message that names the permission and the scope. */
    public function authorize(Actor $actor, string $permission, ?Page $page = null, ?Template $template = null): void
    {
        if ($this->allows($actor, $permission, $page, $template)) {
            return;
        }

        $label = $this->permissions->all()[$permission]['label'] ?? $permission;
        $scope = match (true) {
            $template !== null => " for template [{$template->name}]",
            $page instanceof Page => ' on page ['.($page->path() !== null ? '/'.$page->path() : '#'.$page->id).']',
            default => '',
        };

        throw new AuthorizationException("You do not have permission [{$permission}] ({$label}){$scope}.");
    }

    /**
     * Like authorize() for every page given, as for a whole branch: the pages the grants cover
     * are found in one query, and only the rest are checked one by one (a module's delegated
     * permission may still cover them).
     *
     * @param  list<int>  $pageIds
     */
    public function authorizePages(Actor $actor, string $permission, array $pageIds): void
    {
        $scope = $this->scopeOf($actor, $permission);
        $uncovered = $scope === null ? [] : array_values(array_diff($pageIds, $scope->covering($pageIds)));

        foreach ($this->pages->many($uncovered) as $page) {
            $this->authorize($actor, $permission, $page);
        }
    }

    /**
     * The runner's check before an action runs: $permission is a name, names separated by "|"
     * (any of them, held anywhere), "*" for everyone, or null for superusers only.
     */
    public function authorizeAction(Actor $actor, ?string $permission, string $action): void
    {
        $names = $permission === null ? [] : explode('|', $permission);
        $isAllowed = $permission === '*'
            || ($permission === null ? $this->isSuperuser($actor) : array_any($names, fn (string $name): bool => $this->allowsSomewhere($actor, $name)));

        if (! $isAllowed) {
            throw new AuthorizationException($permission === null
                ? "Only superusers may run [{$action}]."
                : 'You do not have permission ['.implode('] or [', $names)."] needed by [{$action}].");
        }
    }

    /**
     * Held anywhere: directly, or for page permissions through a module's delegated permission,
     * which the action's own check then limits to that module's branch.
     */
    private function allowsSomewhere(Actor $actor, string $permission): bool
    {
        if ($this->allows($actor, $permission)) {
            return true;
        }

        return str_starts_with($permission, 'pages.')
            && array_any(array_unique($this->permissions->delegations()), fn (string $delegate): bool => $this->allows($actor, $delegate));
    }

    /**
     * The pages a permission reaches, for filtering lists: null when nothing limits it (the
     * console, superusers, open mode before any role, or one grant without templates and branches).
     * For page permissions, the branches of modules whose permission the user holds count too.
     */
    public function scopeOf(Actor $actor, string $permission): ?PageScope
    {
        if ($this->isSuperuser($actor)) {
            return null;
        }

        $grants = array_values(array_filter($this->rightsOf($actor)['grants'] ?? [], fn (RoleGrant $grant): bool => $grant->permission === $permission));
        $moduleTemplates = str_starts_with($permission, 'pages.')
            ? array_keys(array_filter($this->permissions->delegations(), fn (string $delegate): bool => $this->allows($actor, $delegate)))
            : [];

        return $this->scopeRules->scope($grants, array_map(strval(...), $moduleTemplates));
    }

    public function isSuperuser(Actor $actor): bool
    {
        if ($this->hasEveryRight($actor)) {
            return true;
        }

        return $this->rightsOf($actor)['superuser'] ?? false;
    }

    /** The console and the system always; anyone but a visitor in open mode before any role. */
    private function hasEveryRight(Actor $actor): bool
    {
        return in_array($actor->kind, [Actor::CLI, Actor::SYSTEM], true)
            || ($actor->kind !== Actor::GUEST && ! $this->isEnforced());
    }

    /**
     * True unless lynx.permissions.open_until_roles is on and no user holds a role yet: then
     * every actor passes, as before ADR-0078.
     */
    public function isEnforced(): bool
    {
        return $this->isEnforced ??= ! Value::bool(config('lynx.permissions.open_until_roles')) || RoleAssignment::query()->exists();
    }

    public function flush(): void
    {
        $this->isEnforced = null;
        $this->rights = [];
    }

    /**
     * @return array{superuser: bool, grants: list<RoleGrant>}|null
     */
    private function rightsOf(Actor $actor): ?array
    {
        if ($actor->id !== null) {
            $key = 'user:'.$actor->id;
            $roleIds = RoleAssignment::query()->where('user_id', (string) $actor->id)->pluck('role_id')->all();

            return array_key_exists($key, $this->rights) ? $this->rights[$key] : $this->rights[$key] = $this->rightsFor(Role::query()->whereKey($roleIds)->with('grants')->get()->all());
        }

        $anonymous = Value::string(config('lynx.permissions.anonymous_role'));

        if ($anonymous === '' || ! in_array($actor->kind, [Actor::MCP, Actor::AI], true)) {
            return null;
        }

        return $this->rights['role:'.$anonymous] ??= $this->rightsFor(Role::query()->where('name', $anonymous)->with('grants')->get()->all());
    }

    /**
     * @param  array<int, Role>  $roles
     * @return array{superuser: bool, grants: list<RoleGrant>}
     */
    private function rightsFor(array $roles): array
    {
        $grants = [];

        foreach ($roles as $role) {
            array_push($grants, ...$role->grants->all());
        }

        return ['superuser' => array_any($roles, fn (Role $role): bool => $role->is_superuser), 'grants' => $grants];
    }

    /** The permission a module delegated for the branch the page is in, if any. */
    private function delegateFor(?Page $page): ?string
    {
        $delegations = $this->permissions->delegations();

        if ($page?->id === null || $delegations === []) {
            return null;
        }

        foreach ($this->pages->many($this->ancestry->idsOf($page->id)) as $ancestor) {
            $permission = $delegations[(string) $ancestor->template()->name] ?? null;

            if ($permission !== null) {
                return $permission;
            }
        }

        return null;
    }

    private function covers(RoleGrant $grant, ?string $template, ?Page $page): bool
    {
        if ($template !== null && $grant->templates !== null && $grant->templates !== [] && ! in_array($template, $grant->templates, true)) {
            return false;
        }

        if ($page?->id === null || $grant->branches === null || $grant->branches === []) {
            return true;
        }

        return array_intersect($this->ancestry->idsOf($page->id), $grant->branches) !== [];
    }
}
