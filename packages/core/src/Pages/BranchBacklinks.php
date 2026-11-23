<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Query\Backlinks;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Pages outside a branch that reference a page in it, through any field that references pages.
 * Trashing or deleting the branch leaves their references pointing at pages visitors no longer
 * see, so the trash and delete actions list them in their change for the editor to check first.
 *
 * @internal
 */
final readonly class BranchBacklinks
{
    public function __construct(private Backlinks $backlinks, private Trash $trash) {}

    /**
     * @param  list<int>  $branch  the branch's page ids
     * @return list<int> ids of the referencing pages, lowest first; pages in the trash are left out
     */
    public function outside(array $branch): array
    {
        $referencing = array_values(array_diff($this->backlinks->referencing($branch), $branch));

        return array_values(array_diff($referencing, $this->trash->heldAmong($referencing)));
    }

    /**
     * The same for several branches, each given by its top page, as a bulk trash moves them.
     *
     * @param  list<int>  $tops
     * @return list<int>
     */
    public function outsideBranches(array $tops): array
    {
        return $tops === [] ? [] : $this->outside(Value::ints(DB::table(Tables::PAGE_CLOSURE)->whereIn('ancestor_id', $tops)->pluck('descendant_id')->all()));
    }

    /**
     * The end of a change's description that names the referencing pages, empty without any.
     *
     * @param  list<int>  $referencing
     */
    public static function note(array $referencing): string
    {
        return $referencing === [] ? '' : '; referenced by page(s) '.implode(', ', $referencing);
    }
}
