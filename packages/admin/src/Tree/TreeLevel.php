<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

/**
 * One page of a parent's children, with pager details.
 *
 * @internal
 */
final readonly class TreeLevel
{
    /**
     * @param  list<TreeBranch>  $branches
     * @param  bool  $isComplete  false when some children are hidden from the user: positions
     *                            among the visible rows are not positions among all siblings, so
     *                            the level cannot be reordered
     */
    public function __construct(
        public int $parentId,
        public array $branches,
        public int $total,
        public int $page,
        public int $perPage,
        public bool $isComplete = true,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPager(): bool
    {
        return $this->lastPage() > 1;
    }

    /**
     * Page numbers to show: first, last and a window around the current page;
     * null marks a gap ("…").
     *
     * @return list<int|null>
     */
    public function pagerLinks(int $window = 2): array
    {
        $links = [];
        $previous = 0;

        foreach (range(1, $this->lastPage()) as $number) {
            $isEdge = $number === 1 || $number === $this->lastPage();

            if (! $isEdge && abs($number - $this->page) > $window) {
                continue;
            }

            if ($number - $previous > 1) {
                $links[] = null;
            }

            $links[] = $previous = $number;
        }

        return $links;
    }

    /** Absolute 0-based position of the first row on this page. */
    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
