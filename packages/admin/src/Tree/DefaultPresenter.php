<?php

declare(strict_types=1);

namespace LynxCms\Admin\Tree;

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Title field in the current language, falling back to the page name; template name as detail.
 *
 * @internal
 */
final class DefaultPresenter implements TreePresenter
{
    public function title(Page $page): string
    {
        $title = trim(Value::string($page->get('title')));

        return $title !== '' ? $title : ($page->name() !== '' ? $page->name() : '/');
    }

    public function detail(Page $page): string
    {
        return (string) $page->template()->name;
    }
}
