<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Access;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use LynxCms\PagePassword\PagePasswordSchema;
use LynxCms\PagePassword\StoredPassword;

/**
 * The pages that have a password, on templates that still have the field: usually few, so they
 * are loaded once and forgotten after any page or template change. A password the app key can
 * no longer read still protects its page, until an editor sets it again.
 *
 * @internal
 */
final class ProtectedRoots
{
    /** @var array<int, StoredPassword>|null */
    private ?array $passwords = null;

    /** @return array<int, StoredPassword> page id => password */
    public function passwords(): array
    {
        return $this->passwords ??= $this->load();
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_keys($this->passwords());
    }

    /**
     * How many pages lie below each protected page.
     *
     * @return array<int, int> page id => pages below it
     */
    public function pagesBelow(): array
    {
        $counts = DB::table(Tables::PAGE_CLOSURE)->whereIn('ancestor_id', $this->ids())->where('depth', '>', 0)
            ->groupBy('ancestor_id')->selectRaw('ancestor_id, count(*) as pages')->pluck('pages', 'ancestor_id');
        $below = [];

        foreach ($counts as $pageId => $pages) {
            $below[Value::int($pageId)] = Value::int($pages);
        }

        return $below;
    }

    public function flush(): void
    {
        $this->passwords = null;
    }

    /** @return array<int, StoredPassword> */
    private function load(): array
    {
        $field = lynx()->fields->find(PagePasswordSchema::FIELD);

        if ($field === null) {
            return [];
        }

        $rows = DB::table($field->table().' as pw')
            ->join(Tables::PAGES.' as p', 'p.id', '=', 'pw.page_id')
            ->join(Tables::TEMPLATE_FIELDS.' as tf', fn (JoinClause $join) => $join->on('tf.template_id', '=', 'p.template_id')->where('tf.field_id', '=', $field->id))
            ->whereNull('p.draft_of')
            ->whereNotNull('pw.data')
            ->pluck('pw.data', 'pw.page_id');

        $passwords = [];

        foreach ($rows as $pageId => $stored) {
            $passwords[Value::int($pageId)] = new StoredPassword(Value::string($stored));
        }

        return $passwords;
    }
}
