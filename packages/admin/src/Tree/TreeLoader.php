<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminBranches;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Query\PageScope;

/**
 * Builds the visible part of the page tree through the public pages API: one find() and one
 * childCounts() query per expanded level, so collapsed branches cost nothing. With details, each row
 * also carries its languages, status and last change (TreeDetails). With a scope, only
 * the pages it lets through are listed and counted (AdminAccess::treeScope()). Every status is
 * included, so a row's position is its real position among all siblings (what pages.sort expects).
 *
 * @internal
 */
final class TreeLoader
{
    /** Every status, so positions are real; module branches (AdminBranches) are left out. */
    private readonly string $selector;

    /**
     * @param  list<int>  $expanded  ids of expanded pages
     * @param  array<int, int>  $pages  current pager page per parent id
     */
    public function __construct(
        private readonly array $expanded,
        private array $pages,
        private readonly int $perPage,
        private readonly TreePresenter $presenter = new DefaultPresenter,
        private readonly ?PageScope $scope = null,
        private readonly ?TreeDetails $details = null,
    ) {
        $hidden = app(AdminBranches::class)->rootTemplates();
        $this->selector = 'include=all'.($hidden === [] ? '' : ', template!='.implode('|', $hidden));
    }

    /** The root page as a branch, with its children loaded when it is expanded. */
    public function branch(Page $root): TreeBranch
    {
        $id = (int) $root->id;
        $count = lynx()->pages->childCounts([$id], $this->selector, $this->scope)[$id] ?? 0;

        return $this->toBranch($this->node($root, 0, $count, isTreeRoot: true));
    }

    private function trashId(): ?int
    {
        return lynx()->pages->trashId();
    }

    private function level(TreeNode $parent): TreeLevel
    {
        $parentId = $parent->id;
        $lastPage = max(1, (int) ceil($parent->childCount / $this->perPage));
        $page = min(max(1, $this->pages[$parentId] ?? 1), $lastPage);
        $start = ($page - 1) * $this->perPage;
        $children = lynx()->pages->find('parent='.$parentId.', '.$this->selector.", start={$start}, limit={$this->perPage}", scope: $this->scope);
        $counts = lynx()->pages->childCounts($children->ids(), $this->selector, $this->scope);
        $drafts = array_flip(lynx()->pages->withDrafts($children->ids()));
        $branches = [];

        foreach (array_values($children->map(fn (Page $child): Page => $child)) as $index => $child) {
            $place = $parent->childPlace((int) $child->id, $this->trashId());
            $node = $this->node($child, $start + $index, $counts[(int) $child->id] ?? 0, place: $place, hasDraft: isset($drafts[(int) $child->id]));
            $branches[] = $this->toBranch($node);
        }

        return new TreeLevel($parentId, $branches, $children->total(), $page, $this->perPage, $this->isComplete($parentId, $parent->childCount));
    }

    /** Whether the user sees every child of the parent, so rows can be reordered. */
    public function isComplete(int $parentId, ?int $visibleCount = null): bool
    {
        if (! $this->scope instanceof PageScope) {
            return true;
        }

        $visibleCount ??= lynx()->pages->childCounts([$parentId], $this->selector, $this->scope)[$parentId] ?? 0;

        return $visibleCount === (lynx()->pages->childCounts([$parentId], $this->selector)[$parentId] ?? 0);
    }

    private function toBranch(TreeNode $node): TreeBranch
    {
        $isOpen = $node->hasChildren() && in_array($node->id, $this->expanded, true);

        return new TreeBranch($node, $isOpen ? $this->level($node) : null);
    }

    private function node(Page $page, int $position, int $childCount, bool $isTreeRoot = false, string $place = TreeNode::SITE, bool $hasDraft = false): TreeNode
    {
        return new TreeNode(
            id: (int) $page->id,
            parentId: $page->parentId,
            position: $position,
            title: $this->presenter->title($page),
            detail: $this->presenter->detail($page),
            url: $page->template()->hasUrl() ? $page->url() : '',
            status: $page->status,
            childCount: $childCount,
            isTreeRoot: $isTreeRoot,
            can: app(AdminAccess::class)->onPage($page),
            place: $place,
            hasDraft: $hasDraft,
            markers: app(TreeMarkers::class)->for($page),
            allowsChildren: $page->template()->allowsChildren(),
            details: $this->details?->for($page),
        );
    }
}
