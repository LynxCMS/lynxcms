<?php

declare(strict_types=1);

namespace LynxCms\Menus\Reading;

/**
 * Marks the item whose URL is the current request's, and the items above it, on a built menu.
 * Menus are built and stored without it (ADR-0068), so one build serves every page.
 *
 * @internal
 */
final readonly class ActiveTrail
{
    public function __construct(private string $path, private string $host) {}

    public static function forRequest(): self
    {
        return new self('/'.trim(request()->getPathInfo(), '/'), request()->getHost());
    }

    public function mark(Menu $menu): Menu
    {
        return new Menu($menu->id, $menu->name, $menu->title, $menu->class, $this->items($menu->items));
    }

    /**
     * @param  list<MenuItem>  $items
     * @return list<MenuItem>
     */
    private function items(array $items): array
    {
        return array_map(function (MenuItem $item): MenuItem {
            $children = $this->items($item->children);
            $isActive = $item->url !== null && $this->isCurrent($item->url);
            $inTrail = $isActive || array_any($children, fn (MenuItem $child): bool => $child->isInActiveTrail);

            return new MenuItem($item->id, $item->kind, $item->label, $item->url, $item->target, $item->rel, $item->class, $item->depth, $isActive, $inTrail, $children, $item->pageId, $item->iconBefore, $item->iconAfter);
        }, $items);
    }

    /** A URL is current when its path matches the request path; other hosts never match. */
    private function isCurrent(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || isset($parts['host']) && $parts['host'] !== $this->host) {
            return false;
        }

        return '/'.trim($parts['path'] ?? '', '/') === $this->path && ! str_starts_with($url, '#');
    }
}
