<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;

/**
 * Finds pages for the admin's pickers (page reference fields, the link dialog, the menu editor):
 * pages the user may view, published or not. Titles holding the text come first, anywhere in a
 * word; then pages whose search index holds every word (ADR-0054), so text in searchable fields
 * finds pages too.
 *
 * @internal
 */
final class PageSearch
{
    /**
     * @param  list<string>  $templates  template names to limit to; empty means any
     */
    public function find(string $text, int $limit, array $templates = []): PageArray
    {
        // Inside a double-quoted selector value commas and pipes are literal; only quotes could end it.
        $search = trim(str_replace(['"', "\0"], '', $text));

        if ($search === '') {
            return new PageArray([]);
        }

        $only = ', include=all'.($templates === [] ? '' : ', template='.implode('|', $templates));
        $byTitle = $this->select('title%="'.$search.'"'.$only.', limit='.$limit);
        $left = $limit - $byTitle->count();

        if ($left <= 0) {
            return $byTitle;
        }

        // Asks for enough to fill the list after dropping pages the titles already found.
        $found = $byTitle->ids();
        $byText = $this->select('search*="'.$search.'"'.$only.', limit='.$limit);
        $more = array_filter($byText->all(), fn (Page $page): bool => ! in_array($page->id, $found, true));

        return new PageArray([...$byTitle->all(), ...array_slice(array_values($more), 0, $left)]);
    }

    private function select(string $selector): PageArray
    {
        return lynx()->pages->find($selector, scope: app(AdminAccess::class)->viewScope());
    }
}
