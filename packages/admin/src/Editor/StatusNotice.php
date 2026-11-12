<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;

/**
 * The notice after a status tick or a row's status action applies at once (Settings tab, page
 * tree, Children tab): the page's title and the flag's new state, read from the saved page.
 *
 * @internal
 */
final class StatusNotice
{
    public function for(Page|int $page, string $flag): string
    {
        $page = lynx()->pages->get($page instanceof Page ? (int) $page->id : $page);

        if (! $page instanceof Page || ! isset(PageStatus::NAMES[$flag])) {
            return __('lynx-admin::pages.status_changed.saved');
        }

        $state = $page->hasStatus(PageStatus::NAMES[$flag]) ? 'on' : 'off';

        return __("lynx-admin::pages.status_changed.{$flag}.{$state}");
    }
}
