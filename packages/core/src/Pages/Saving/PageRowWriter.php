<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Saving;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\SiblingOrder;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * The lynx_pages row of a saved page: inserted with its closure rows (placed last among its
 * siblings unless it has a position), or updated with a raised revision.
 *
 * @internal
 */
final readonly class PageRowWriter
{
    public function __construct(private SiblingOrder $order) {}

    /** @return int the page id; the page's id, sort, revision and modifiedBy are set too */
    public function write(Page $page, string $actor, CarbonInterface $now): int
    {
        $isNew = $page->id === null;
        $id = $isNew ? $this->insert($page, $actor, $now) : $this->update($page->id, $page, $actor, $now);

        $page->id = $id;

        if ($isNew && $page->parentId !== null && $this->order->hasSystemChild($page->parentId, $id)) {
            // A new page goes before system pages (a module's branch, the trash), which stay last.
            $this->order->compact($page->parentId);
            $page->sort = Value::int(DB::table(Tables::PAGES)->where('id', $id)->value('sort'));
        }

        $page->revision = Value::int(DB::table(Tables::PAGES)->where('id', $id)->value('revision'));
        $page->modifiedBy = $actor;

        return $id;
    }

    private function insert(Page $page, string $actor, CarbonInterface $now): int
    {
        $page->sort = $page->sort ?: Value::int(DB::table(Tables::PAGES)->where('parent_id', $page->parentId)->max('sort')) + 1;
        $id = (int) DB::table(Tables::PAGES)->insertGetId([
            'parent_id' => $page->parentId,
            'template_id' => $page->templateId,
            'status' => $page->status,
            'sort' => $page->sort,
            'created_by' => $actor,
            'modified_by' => $actor,
            'published_at' => $page->hasStatus(PageStatus::UNPUBLISHED) ? null : $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table(Tables::PAGE_CLOSURE)->insertUsing(
            ['ancestor_id', 'descendant_id', 'depth'],
            DB::table(Tables::PAGE_CLOSURE)->where('descendant_id', $page->parentId ?? 0)->selectRaw('ancestor_id, ?, depth + 1', [$id]),
        );
        DB::table(Tables::PAGE_CLOSURE)->insert(['ancestor_id' => $id, 'descendant_id' => $id, 'depth' => 0]);

        return $id;
    }

    private function update(int $id, Page $page, string $actor, CarbonInterface $now): int
    {
        DB::table(Tables::PAGES)->where('id', $id)->update([
            'status' => $page->status,
            'modified_by' => $actor,
            'updated_at' => $now,
            'revision' => DB::raw('revision + 1'),
            'published_at' => $page->publishedAt ?? ($page->hasStatus(PageStatus::UNPUBLISHED) ? null : $now),
        ]);

        return $id;
    }
}
