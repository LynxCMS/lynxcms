<?php

declare(strict_types=1);

namespace LynxCms\Admin;

use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Query\PageScope;

/**
 * What the current admin user may do, for hiding what they cannot use (ADR-0033). It asks for the
 * actor the actions run as, so a hidden button and a refused action always agree. The actions
 * still check everything themselves.
 *
 * @api
 */
final readonly class AdminAccess
{
    /** The page actions the tree and the editor offer, by key. */
    private const array PAGE_ACTIONS = ['edit' => 'pages.edit', 'create' => 'pages.create', 'move' => 'pages.move', 'publish' => 'pages.publish', 'status' => 'pages.status', 'delete' => 'pages.delete', 'template' => 'pages.template'];

    public function __construct(private Authorizer $authorizer, private ActionRunner $runner) {}

    public function allows(string $permission, ?Page $page = null, ?Template $template = null): bool
    {
        return $this->authorizer->allows($this->actor(), $permission, $page, $template);
    }

    /**
     * The pages the page tree shows: those the user may view or act on (any page action) and the
     * pages above them, as the way there; null shows every page.
     */
    public function treeScope(): ?PageScope
    {
        $scopes = [];

        foreach (['pages.view', ...array_values(self::PAGE_ACTIONS)] as $permission) {
            $scope = $this->authorizer->scopeOf($this->actor(), $permission);

            if (! $scope instanceof PageScope) {
                return null;
            }

            $scopes[] = $scope;
        }

        return PageScope::union(...$scopes)->withAncestors();
    }

    /** The pages the user may view, for search results such as page pickers; null for all. */
    public function viewScope(): ?PageScope
    {
        return $this->authorizer->scopeOf($this->actor(), 'pages.view');
    }

    /**
     * The given pages the current user may view, in their order: for components that load pages
     * by ids a request could change.
     *
     * @param  list<int>  $pageIds
     * @return list<int>
     */
    public function viewable(array $pageIds): array
    {
        $scope = $this->viewScope();

        if ($scope === null || $pageIds === []) {
            return $pageIds;
        }

        $covered = $scope->covering($pageIds);

        return array_values(array_filter($pageIds, fn (int $id): bool => in_array($id, $covered, true)));
    }

    public function isSuperuser(): bool
    {
        return $this->authorizer->isSuperuser($this->actor());
    }

    /**
     * The page actions a user may take on one page: edit, create (children), move, publish,
     * status, delete, template.
     *
     * @return array<string, bool>
     */
    public function onPage(Page $page): array
    {
        $actions = self::PAGE_ACTIONS;

        if ($this->isSuperuser()) {
            return array_map(fn (): bool => true, $actions);
        }

        return array_map(fn (string $permission): bool => $this->allows($permission, $page), $actions);
    }

    private function actor(): Actor
    {
        return $this->runner->actor();
    }
}
