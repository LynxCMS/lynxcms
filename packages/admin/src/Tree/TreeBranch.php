<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

/**
 * A node plus its loaded children; children is null while the node is collapsed.
 *
 * @internal
 */
final readonly class TreeBranch
{
    public function __construct(public TreeNode $node, public ?TreeLevel $children = null) {}

    public function isExpanded(): bool
    {
        return $this->children instanceof TreeLevel;
    }
}
