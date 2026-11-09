<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminBranches;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Query\PageScope;

/**
 * A page's children as the admin lists them, for the Children tab: every status, without the trash
 * and module branches (AdminBranches), as the page tree shows them, within the pages the user may
 * see in the tree (AdminAccess::treeScope()).
 *
 * @internal
 */
final readonly class ChildList
{
    public function __construct(private AdminAccess $access, private AdminBranches $branches) {}

    /** Selector clauses for every child the tree lists, without the parent. */
    public function everyChild(): string
    {
        $hidden = $this->branches->rootTemplates();
        $trash = lynx()->pages->trashId();

        return 'include=all'
            .($hidden === [] ? '' : ', template!='.implode('|', $hidden))
            .($trash === null ? '' : ', id!='.$trash);
    }

    public function selector(int $parentId): string
    {
        return 'parent='.$parentId.', '.$this->everyChild();
    }

    public function hasChildren(Page $page): bool
    {
        return $page->id !== null && $this->count((int) $page->id, $this->scope()) > 0;
    }

    /** Whether the user sees every child, so a position in the list is a real position. */
    public function isComplete(int $parentId): bool
    {
        return $this->count($parentId, $this->scope()) === $this->count($parentId, null);
    }

    public function scope(): ?PageScope
    {
        return $this->access->treeScope();
    }

    private function count(int $parentId, ?PageScope $scope): int
    {
        return lynx()->pages->childCounts([$parentId], $this->everyChild(), $scope)[$parentId] ?? 0;
    }
}
