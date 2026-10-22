<?php

declare(strict_types=1);

namespace LynxCms\Menus\Reading;

/**
 * One resolved menu entry in the current language. Items added automatically from a page's
 * children have no id.
 *
 * @api
 */
final readonly class MenuItem
{
    /**
     * @param  'section'|'page'|'link'  $kind
     * @param  list<MenuItem>  $children
     */
    public function __construct(
        public ?int $id,
        public string $kind,
        public string $label,
        public ?string $url,
        public ?string $target,
        public ?string $rel,
        public ?string $class,
        public int $depth,
        public bool $isActive,
        public bool $isInActiveTrail,
        public array $children = [],
        public ?int $pageId = null,
        public ?string $iconBefore = null,
        public ?string $iconAfter = null,
    ) {}

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /** False for section labels (and links whose URL was refused): render them as text. */
    public function hasUrl(): bool
    {
        return $this->url !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'label' => $this->label,
            'url' => $this->url,
            'target' => $this->target,
            'rel' => $this->rel,
            'class' => $this->class,
            'depth' => $this->depth,
            'active' => $this->isActive,
            'active_trail' => $this->isInActiveTrail,
            'page_id' => $this->pageId,
            'icon_before' => $this->iconBefore,
            'icon_after' => $this->iconAfter,
            'children' => array_map(fn (MenuItem $child): array => $child->toArray(), $this->children),
        ];
    }
}
