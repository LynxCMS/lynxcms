<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Write side of sibling order (ADR-0024). Sort values are kept as contiguous 0-based
 * positions so a position from the admin, MCP or an agent maps directly to a row.
 *
 * @internal
 */
final readonly class SiblingOrder
{
    public function __construct(private Trash $trash) {}

    /**
     * Place a page at a 0-based position among its parent's children (clamped to the end)
     * and renumber the siblings. Only rows whose sort value changes are written. System pages
     * (a module's branch such as /_menus/, the trash) stay after the others, the trash last, so
     * the admin can hide them without shifting anyone's position.
     *
     * @return int the page's new position
     */
    public function place(int $parentId, int $pageId, int $position): int
    {
        $siblings = $this->siblings($parentId);
        unset($siblings[$pageId]);
        [$pages, $system] = $this->split($siblings);

        if ($this->isSystem($pageId)) {
            $this->write([...$pages, ...$this->trashLast([...$system, $pageId])]);

            return (int) array_search($pageId, [...$pages, ...$this->trashLast([...$system, $pageId])], true);
        }

        $position = max(0, min($position, count($pages)));
        array_splice($pages, $position, 0, [$pageId]);
        $this->write([...$pages, ...$system]);

        return $position;
    }

    /**
     * Renumber a parent's children 0..n in their current order, closing gaps left by a move,
     * with system pages last (ADR-0035).
     */
    public function compact(int $parentId): void
    {
        [$pages, $system] = $this->split($this->siblings($parentId));

        $this->write([...$pages, ...$system]);
    }

    /**
     * Whether the parent has a system child other than $except: then a new page must go before it.
     */
    public function hasSystemChild(int $parentId, int $except): bool
    {
        $statuses = array_values(array_filter(range(0, 2 * PageStatus::SYSTEM - 1), fn (int $status): bool => ($status & PageStatus::SYSTEM) !== 0));

        return DB::table(Tables::PAGES)->where('parent_id', $parentId)->where('id', '!=', $except)->whereIn('status', $statuses)->exists();
    }

    /** The 0-based position of a page among its siblings. */
    public function positionOf(int $parentId, int $pageId): int
    {
        $index = array_search($pageId, $this->siblingIds($parentId), true);

        return $index === false ? 0 : $index;
    }

    /**
     * @return list<int>
     */
    private function siblingIds(int $parentId): array
    {
        return array_keys($this->siblings($parentId));
    }

    /**
     * The parent's children in order, id => status.
     *
     * @return array<int, int>
     */
    private function siblings(int $parentId): array
    {
        // Row locks inside the action's transaction: concurrent reorders of one parent wait
        // instead of overwriting each other's renumbering (a no-op on SQLite).
        $siblings = [];

        foreach (DB::table(Tables::PAGES)->where('parent_id', $parentId)->orderBy('sort')->orderBy('id')->lockForUpdate()->get(['id', 'status']) as $row) {
            $siblings[Value::int($row->id)] = Value::int($row->status);
        }

        return $siblings;
    }

    /**
     * Ordinary pages and system pages, each in their order, the trash last.
     *
     * @param  array<int, int>  $siblings  id => status
     * @return array{0: list<int>, 1: list<int>}
     */
    private function split(array $siblings): array
    {
        $pages = array_keys(array_filter($siblings, fn (int $status): bool => ($status & PageStatus::SYSTEM) === 0));
        $system = array_keys(array_filter($siblings, fn (int $status): bool => ($status & PageStatus::SYSTEM) !== 0));

        return [$pages, $this->trashLast($system)];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function trashLast(array $ids): array
    {
        $trash = $this->trash->rootId();

        return $trash !== null && in_array($trash, $ids, true) ? [...array_values(array_diff($ids, [$trash])), $trash] : $ids;
    }

    private function isSystem(int $pageId): bool
    {
        return (Value::int(DB::table(Tables::PAGES)->where('id', $pageId)->value('status')) & PageStatus::SYSTEM) !== 0;
    }

    /**
     * @param  list<int>  $orderedIds
     */
    private function write(array $orderedIds): void
    {
        $current = DB::table(Tables::PAGES)->whereIn('id', $orderedIds)->pluck('sort', 'id')->all();

        foreach ($orderedIds as $sort => $id) {
            if (Value::int($current[$id] ?? -1) !== $sort) {
                DB::table(Tables::PAGES)->where('id', $id)->update(['sort' => $sort]);
            }
        }
    }
}
