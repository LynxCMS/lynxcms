<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

use LynxCms\Core\Pages\PageStatus;

/**
 * One row of the page tree: what the view needs, nothing the client can write back.
 *
 * @internal
 */
final readonly class TreeNode
{
    /** Where the page is: in the site, the trash page itself, the top of a trashed branch, or deeper in the trash. */
    public const string SITE = 'site';

    public const string TRASH = 'trash';

    public const string TRASHED = 'trashed';

    public const string IN_TRASH = 'in-trash';

    /**
     * @param  array<string, bool>  $can  page actions the current user may take (AdminAccess::onPage)
     * @param  list<array{key: string, icon: string, label: string}>  $markers  icons modules show beside the page (TreeMarkers)
     * @param  bool  $allowsChildren  false when the page's template allows no children (ADR-0032): no New
     * @param  array{languages: list<array{code: string, label: string, state: string}>, status: string, changed: ?string, changedAt: ?string, changedBy: ?string}|null  $details  the Details view's columns (TreeDetails), null in the simple view
     */
    public function __construct(
        public int $id,
        public ?int $parentId,
        public int $position,
        public string $title,
        public string $detail,
        public string $url,
        public int $status,
        public int $childCount,
        public bool $isTreeRoot = false,
        public array $can = [],
        public string $place = self::SITE,
        public bool $hasDraft = false,
        public array $markers = [],
        public bool $allowsChildren = true,
        public ?array $details = null,
    ) {}

    public function isTrash(): bool
    {
        return $this->place === self::TRASH;
    }

    /** The top page of a trashed branch: it can be restored or deleted for good. */
    public function isTrashed(): bool
    {
        return $this->place === self::TRASHED;
    }

    public function isInTrash(): bool
    {
        return in_array($this->place, [self::TRASHED, self::IN_TRASH], true);
    }

    /** The place of a child of this page. */
    public function childPlace(int $childId, ?int $trashId): string
    {
        return match ($this->place) {
            self::TRASH => self::TRASHED,
            self::TRASHED, self::IN_TRASH => self::IN_TRASH,
            default => $childId === $trashId ? self::TRASH : self::SITE,
        };
    }

    /** Whether the current user may take a page action here: edit, create, move, publish, status, delete. */
    public function can(string $action): bool
    {
        return $this->can[$action] ?? false;
    }

    public function isRoot(): bool
    {
        return $this->parentId === null;
    }

    /** False for pages on templates without URLs (settings, menu items): no View link. */
    public function hasUrl(): bool
    {
        return $this->url !== '';
    }

    public function hasChildren(): bool
    {
        return $this->childCount > 0;
    }

    public function isHidden(): bool
    {
        return $this->hasFlag(PageStatus::HIDDEN);
    }

    public function isUnpublished(): bool
    {
        return $this->hasFlag(PageStatus::UNPUBLISHED);
    }

    public function isLocked(): bool
    {
        return $this->hasFlag(PageStatus::LOCKED);
    }

    public function isSystem(): bool
    {
        return $this->hasFlag(PageStatus::SYSTEM);
    }

    public function canMove(): bool
    {
        return $this->isMovable() && $this->can('move');
    }

    public function canDelete(): bool
    {
        return $this->isMovable() && $this->can('delete');
    }

    public function canChangeStatus(): bool
    {
        return ! $this->isRoot() && ! $this->isTreeRoot && ! $this->isSystem() && ($this->can('status') || $this->can('publish'));
    }

    /**
     * The home page, the tree's own root, system and locked pages stay where they are; in the
     * trash only whole branches move (restore), deleted for good or dragged back out.
     */
    private function isMovable(): bool
    {
        return ! $this->isRoot() && ! $this->isTreeRoot && ! $this->isSystem() && ! $this->isLocked() && $this->place !== self::IN_TRASH;
    }

    private function hasFlag(int $flag): bool
    {
        return ($this->status & $flag) === $flag;
    }
}
