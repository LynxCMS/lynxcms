<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Unpublished pages that have children: the roots of offline branches (ADR-0026). Drafts are
 * usually leaves, so the list is short or empty, and selectors can skip the branch filter entirely
 * when it is empty. Loaded once per request and flushed after every action.
 *
 * @internal
 */
final class UnpublishedBranches implements Flushable
{
    /** @var list<int>|null */
    private ?array $ids = null;

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        // Unqualified columns in single-table queries, so the host's table prefix cannot break them.
        return $this->ids ??= Value::ints(DB::table(Tables::PAGES)
            ->whereIn('parent_id', fn (Builder $q): Builder => $q->select('id')->from(Tables::PAGES)->whereRaw('(status & ?) <> 0', [PageStatus::UNPUBLISHED]))
            ->distinct()
            ->pluck('parent_id')
            ->all());
    }

    public function flush(): void
    {
        $this->ids = null;
    }
}
