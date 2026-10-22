<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures;

use LynxCms\Admin\Tree\TreePresenter;
use LynxCms\Core\Pages\Page;

final class ShoutingPresenter implements TreePresenter
{
    public function title(Page $page): string
    {
        return mb_strtoupper($page->name()).'!';
    }

    public function detail(Page $page): string
    {
        return 'detail of '.$page->name();
    }
}
