<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Puts a page and everything under it below another parent: the closure table, the parent
 * columns and the sibling order. Checks and paths are the caller's (pages.move, pages.trash,
 * pages.restore).
 *
 * @internal
 */
final readonly class BranchMover
{
    private const int INSERT_CHUNK = 500;

    public function __construct(private SiblingOrder $order) {}

    /**
     * @param  int  $position  0-based position among the new siblings (clamped to the end)
     * @return int number of pages moved
     */
    public function move(Page $page, int $parentId, int $position = PHP_INT_MAX): int
    {
        $from = (int) $page->parentId;
        $moved = $this->relink((int) $page->id, $parentId);

        DB::table(Tables::PAGES)->where('id', $page->id)->update(['parent_id' => $parentId, 'updated_at' => now(), 'revision' => DB::raw('revision + 1')]);
        DB::table(Tables::PAGE_LANGUAGES)->where('page_id', $page->id)->update(['parent_id' => $parentId]);
        $page->parentId = $parentId;
        $page->sort = $this->order->place($parentId, (int) $page->id, $position);
        $this->order->compact($from);

        return $moved;
    }

    /** Closure table move: unlink the subtree from its old ancestors, link it to the new ones. */
    private function relink(int $pageId, int $parentId): int
    {
        $subtree = DB::table(Tables::PAGE_CLOSURE)->where('ancestor_id', $pageId)->pluck('descendant_id')->all();

        DB::table(Tables::PAGE_CLOSURE)->whereIn('descendant_id', $subtree)->whereNotIn('ancestor_id', $subtree)->delete();

        $ancestors = DB::table(Tables::PAGE_CLOSURE)->where('descendant_id', $parentId)->get();
        $links = DB::table(Tables::PAGE_CLOSURE)->where('ancestor_id', $pageId)->get();
        $rows = [];

        foreach ($ancestors as $ancestor) {
            foreach ($links as $link) {
                $rows[] = ['ancestor_id' => $ancestor->ancestor_id, 'descendant_id' => $link->descendant_id, 'depth' => Value::int($ancestor->depth) + Value::int($link->depth) + 1];
            }
        }

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            DB::table(Tables::PAGE_CLOSURE)->insert($chunk);
        }

        return count($subtree);
    }
}
