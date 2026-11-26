<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Saving;

use Closure;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * The page whose values pages.save is checking, for field types that check a value against its
 * page while they normalize it, such as a media field that only accepts files in the page's own
 * folder. Null outside a save, where those checks are skipped.
 *
 * @internal
 */
final class SavingPage
{
    private ?Page $page = null;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function during(Page $page, Closure $callback): mixed
    {
        $previous = $this->page;
        $this->page = $page;

        try {
            return $callback();
        } finally {
            $this->page = $previous;
        }
    }

    /**
     * The ids whose folders the page's files may sit in: its own, and for a draft the page it
     * belongs to. Null outside a save, and for a page not created yet.
     *
     * @return list<int>|null
     */
    public function folders(): ?array
    {
        if ($this->page?->id === null) {
            return null;
        }

        $id = (int) $this->page->id;
        $draftOf = DB::table(Tables::PAGES)->where('id', $id)->value('draft_of');

        return $draftOf === null ? [$id] : [$id, Value::int($draftOf)];
    }
}
