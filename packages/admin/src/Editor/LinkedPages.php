<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Admin\Tree\DefaultPresenter;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;

/**
 * Pages that link to others, as the admin lists them: each one's title, to open in the editor, and
 * where it sits (its template and parent). Feed it $lynx->pages->backlinks(); the Settings tab's
 * "Linked from" panel and the trash's warning use it, through <x-lynx-admin::linked-pages>.
 *
 * @internal
 */
final class LinkedPages
{
    /**
     * @return list<array{id: int, title: string, context: string}>
     */
    public static function rows(PageArray $pages): array
    {
        return array_values($pages->map(self::row(...)));
    }

    /**
     * @return array{id: int, title: string, context: string}
     */
    public static function row(Page $page): array
    {
        $titles = new DefaultPresenter;
        $parent = $page->parent();
        $template = $page->template()->label ?: $page->template()->name;

        return [
            'id' => (int) $page->id,
            'title' => $titles->title($page),
            'context' => $parent instanceof Page
                ? (string) __('lynx-admin::settings.backlinks.context', ['template' => $template, 'parent' => $titles->title($parent)])
                : (string) $template,
        ];
    }
}
