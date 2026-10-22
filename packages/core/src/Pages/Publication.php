<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Whether visitors may see a page: it and every page above it must be published (ADR-0026).
 * Hidden pages stay reachable by URL; hidden only keeps them out of listings and search.
 *
 * @api
 */
final class Publication
{
    public function isPublished(Page $page): bool
    {
        if ($page->hasStatus(PageStatus::UNPUBLISHED)) {
            return false;
        }

        return ! DB::table(Tables::PAGE_CLOSURE)
            ->join(Tables::PAGES, Tables::PAGES.'.id', '=', Tables::PAGE_CLOSURE.'.ancestor_id')
            ->where(Tables::PAGE_CLOSURE.'.descendant_id', $page->id)
            ->where(Tables::PAGE_CLOSURE.'.depth', '>', 0)
            ->pluck(Tables::PAGES.'.status')
            ->contains(fn (mixed $status): bool => (Value::int($status) & PageStatus::UNPUBLISHED) !== 0);
    }
}
