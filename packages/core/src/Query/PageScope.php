<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * Limits a query to the pages some grants cover (ADR-0033). Each rule is one grant: the page
 * must have one of its templates and lie inside one of its branches (the branch page included);
 * null means no limit on that side. A page passes when any rule covers it. No rules: no page.
 * withAncestors() also lets through every page above a covered one, as the way to it in a tree.
 *
 * @api
 */
final readonly class PageScope
{
    /** Ids per query, well below the bound parameter limits of SQLite and PostgreSQL. */
    private const int CHUNK = 1000;

    /** Alias of the pages table for the covered descendants in withAncestors() queries. */
    private const string DESCENDANT = 'lynx_scope_descendant';

    /**
     * @param  list<array{templates: list<int>|null, branches: list<int>|null}>  $rules  template ids and branch page ids, never both null
     */
    public function __construct(public array $rules, public bool $includesAncestors = false) {}

    /** The pages any of the scopes covers. */
    public static function union(self ...$scopes): self
    {
        return new self(array_values(array_unique(array_merge(...array_map(fn (self $scope): array => $scope->rules, $scopes)), SORT_REGULAR)));
    }

    /** The same scope, also letting through the pages above every covered page. */
    public function withAncestors(): self
    {
        return new self($this->rules, includesAncestors: true);
    }

    /**
     * The given pages this scope covers, in a few queries however many there are.
     *
     * @param  list<int>  $pageIds
     * @return list<int>
     */
    public function covering(array $pageIds): array
    {
        $covered = [];

        foreach (array_chunk($pageIds, self::CHUNK) as $chunk) {
            $query = DB::table(Tables::PAGES)->whereIn(Tables::PAGES.'.id', $chunk);
            $this->apply($query);
            array_push($covered, ...Value::ints($query->pluck(Tables::PAGES.'.id')->all()));
        }

        return $covered;
    }

    /** Filters a query on the pages table, or on $pages when it is aliased. */
    public function apply(Builder $query, string $pages = Tables::PAGES): void
    {
        if ($this->rules === []) {
            $query->whereIn($pages.'.id', []);

            return;
        }

        if ($this->includesAncestors) {
            // The closure table holds a row for each page and itself, so a covered page passes too.
            $query->whereExists(function (Builder $below) use ($pages): void {
                $below->selectRaw('1')
                    ->from(Tables::PAGE_CLOSURE)
                    ->join(Tables::PAGES.' as '.self::DESCENDANT, self::DESCENDANT.'.id', '=', Tables::PAGE_CLOSURE.'.descendant_id')
                    ->whereColumn(Tables::PAGE_CLOSURE.'.ancestor_id', $pages.'.id');
                new self($this->rules)->apply($below, self::DESCENDANT);
            });

            return;
        }

        $query->where(function (Builder $any) use ($pages): void {
            foreach ($this->rules as $rule) {
                $any->orWhere(fn (Builder $one) => $this->applyRule($one, $rule, $pages));
            }
        });
    }

    /**
     * @param  array{templates: list<int>|null, branches: list<int>|null}  $rule
     */
    private function applyRule(Builder $query, array $rule, string $pages): void
    {
        if ($rule['templates'] !== null) {
            $query->whereIn($pages.'.template_id', $rule['templates']);
        }

        if ($rule['branches'] !== null) {
            $query->whereExists(fn (Builder $closure) => $closure->selectRaw('1')
                ->from(Tables::PAGE_CLOSURE.' as lynx_scope_branch')
                ->whereColumn('lynx_scope_branch.descendant_id', $pages.'.id')
                ->whereIn('lynx_scope_branch.ancestor_id', $rule['branches']));
        }
    }
}
