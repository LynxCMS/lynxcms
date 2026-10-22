<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

use LynxCms\Core\Pages\Page;

/**
 * Decides what a page tree row shows. Modules that reuse the tree for their own pages (the menu
 * builder shows "Courses" and "Page · /courses/" for a menu item) pass their presenter's class.
 *
 * @api
 */
interface TreePresenter
{
    /** Main text of the row. */
    public function title(Page $page): string;

    /** Short muted text shown with the row's actions, by default the template name. */
    public function detail(Page $page): string;
}
