<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

/**
 * Where the page editor goes after the publish button's menu publishes (PublishesPage): back to
 * the page tree, to the page on the site, to a new page beside it, or to the next page.
 *
 * @internal
 */
enum FollowUp: string
{
    case Exit = 'exit';
    case View = 'view';
    case Add = 'add';
    case Next = 'next';
}
