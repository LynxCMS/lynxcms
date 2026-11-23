<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\References;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\Trash;
use LynxCms\Core\Query\PageFinder;
use LynxCms\Core\Query\PageScope;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * The reports of the page references module (ADR-0145), read from the page references index and
 * limited in the query to the pages the user may view: references that lead nowhere a visitor can
 * go, pages nothing links to, and the pages linked from the most pages.
 *
 * @internal
 */
final readonly class ReferenceReports
{
    /** The broken reasons, in the order a reference is checked for them. */
    public const string MISSING = 'missing';

    public const string TRASHED = 'trashed';

    public const string UNPUBLISHED = 'unpublished';

    /** Alias of the pages table for the referencing page. */
    private const string SOURCE = 'lynx_ref_source';

    /** Alias of the pages table for the linked page. */
    private const string TARGET = 'lynx_ref_target';

    public function __construct(private PageFinder $finder, private Trash $trash) {}

    /**
     * References whose page is gone, in the trash, or unpublished, from pages outside the trash.
     *
     * @return array{rows: list<array{reference: PageReference, reason: string}>, total: int}
     */
    public function broken(?PageScope $scope, int $limit, int $start): array
    {
        $query = DB::table(Tables::PAGE_REFERENCES)
            ->join(Tables::PAGES.' as '.self::SOURCE, self::SOURCE.'.id', '=', Tables::PAGE_REFERENCES.'.page_id')
            ->leftJoin(Tables::PAGES.' as '.self::TARGET, fn (JoinClause $join) => $join->on(self::TARGET.'.id', '=', Tables::PAGE_REFERENCES.'.linked_page_id'));
        $this->outsideTrash($query, self::SOURCE);
        $scope?->apply($query, self::SOURCE);
        $query->where(fn (Builder $broken) => $broken->whereNull(self::TARGET.'.id')
            ->orWhere(self::TARGET.'.status', '&', PageStatus::UNPUBLISHED)
            ->orWhereExists(fn (Builder $trashed) => $this->inTrash($trashed, self::TARGET)));

        $total = (clone $query)->count();
        $rows = $query->orderBy(Tables::PAGE_REFERENCES.'.page_id')->orderBy(Tables::PAGE_REFERENCES.'.field_id')->orderBy(Tables::PAGE_REFERENCES.'.sort')
            ->offset($start)->limit($limit)
            ->get([Tables::PAGE_REFERENCES.'.*', self::TARGET.'.id as target_id', self::TARGET.'.status as target_status']);
        $trashed = $this->trashedAmong(Value::ints($rows->pluck('target_id')->filter()->all()));

        return ['rows' => array_values($rows->map(fn (object $row): array => [
            'reference' => PageReference::fromRow($row),
            'reason' => match (true) {
                $row->target_id === null => self::MISSING,
                in_array(Value::int($row->target_id), $trashed, true) => self::TRASHED,
                default => self::UNPUBLISHED,
            },
        ])->all()), 'total' => $total];
    }

    /**
     * Pages nothing links to, among the pages a selector names (the admin's tree filters), by title.
     *
     * @return array{ids: list<int>, total: int}
     */
    public function unlinked(string $selector, ?PageScope $scope, int $limit, int $start): array
    {
        $compiled = $this->finder->compile(trim($selector.', sort=title', ', '));

        foreach ([$compiled->query, $compiled->countQuery] as $query) {
            $query->whereNotNull(Tables::PAGES.'.parent_id')->whereNotExists(fn (Builder $links) => $links->selectRaw('1')
                ->from(Tables::PAGE_REFERENCES)->whereColumn(Tables::PAGE_REFERENCES.'.linked_page_id', Tables::PAGES.'.id'));
            $scope?->apply($query);
        }

        return [
            'ids' => array_values(array_unique(Value::ints($compiled->query->offset($start)->limit($limit)->pluck(Tables::PAGES.'.id')->all()))),
            'total' => (int) $compiled->countQuery->distinct()->count(Tables::PAGES.'.id'),
        ];
    }

    /**
     * The pages linked from the most pages, with how many.
     *
     * @return list<array{id: int, count: int}>
     */
    public function mostLinked(?PageScope $scope, int $limit): array
    {
        $query = DB::table(Tables::PAGE_REFERENCES)
            ->join(Tables::PAGES, Tables::PAGES.'.id', '=', Tables::PAGE_REFERENCES.'.linked_page_id')
            ->whereNull(Tables::PAGES.'.draft_of');
        $this->outsideTrash($query, Tables::PAGES);
        $scope?->apply($query);

        return array_values($query->groupBy(Tables::PAGE_REFERENCES.'.linked_page_id')
            ->orderByDesc('links')->orderBy(Tables::PAGE_REFERENCES.'.linked_page_id')->limit($limit)
            ->select(Tables::PAGE_REFERENCES.'.linked_page_id')->selectRaw('count(distinct page_id) as links')->get()
            ->map(fn (object $row): array => ['id' => Value::int($row->linked_page_id ?? null), 'count' => Value::int($row->links ?? null)])
            ->all());
    }

    private function outsideTrash(Builder $query, string $pages): void
    {
        $query->whereNotExists(fn (Builder $trashed) => $this->inTrash($trashed, $pages));
    }

    /** The trash page and every page below it (closure rows include the page itself). */
    private function inTrash(Builder $query, string $pages): void
    {
        $query->selectRaw('1')->from(Tables::PAGE_CLOSURE)
            ->where(Tables::PAGE_CLOSURE.'.ancestor_id', $this->trash->rootId() ?? 0)
            ->whereColumn(Tables::PAGE_CLOSURE.'.descendant_id', $pages.'.id');
    }

    /**
     * @param  list<int>  $pageIds
     * @return list<int>
     */
    private function trashedAmong(array $pageIds): array
    {
        $trashId = $this->trash->rootId();

        return $pageIds === [] || $trashId === null ? [] : Value::ints(DB::table(Tables::PAGE_CLOSURE)
            ->where('ancestor_id', $trashId)->whereIn('descendant_id', $pageIds)->pluck('descendant_id')->all());
    }
}
