<?php

declare(strict_types=1);

namespace LynxCms\Finder;

use LynxCms\Finder\Catalog\ConditionCatalog;
use LynxCms\Finder\Catalog\Operator;
use LynxCms\Finder\Search\Condition;
use LynxCms\Finder\Search\ConditionGroup;
use LynxCms\Finder\Search\FinderQuery;

/**
 * Turns a finder search into selector terms. A group that matches all is one alternative holding
 * every condition; a group that matches any is one alternative per condition. With groups that
 * must all match, each group with several alternatives is a named OR set (ADR-0110); a single
 * group, or groups where any may match, put every alternative in one unnamed set. Conditions that
 * are incomplete, or name something the catalog lacks, are left out, so editors' words never
 * reach the selector unchecked.
 *
 * @api
 */
final readonly class SelectorWriter
{
    public function __construct(private ConditionCatalog $catalog, private ConditionWriter $conditions = new ConditionWriter) {}

    /** The terms for the search's conditions, without sort or scope; "" when none is complete. */
    public function terms(FinderQuery $query): string
    {
        $groups = array_values(array_filter(array_map($this->alternatives(...), $query->groups)));

        if ($groups === []) {
            return '';
        }

        if (count($groups) === 1 || ! $query->matchAll) {
            $alternatives = array_merge(...$groups);

            return count($alternatives) === 1 ? implode(', ', $alternatives[0]) : $this->set('', $alternatives);
        }

        $terms = [];

        foreach ($groups as $index => $alternatives) {
            $terms[] = count($alternatives) === 1 ? implode(', ', $alternatives[0]) : $this->set('group'.($index + 1), $alternatives);
        }

        return implode(', ', $terms);
    }

    /**
     * Each way a page can match the group, as a list of clauses.
     *
     * @return list<list<string>>
     */
    private function alternatives(ConditionGroup $group): array
    {
        $clauses = array_values(array_filter(array_map($this->clauses(...), $group->conditions)));

        if ($clauses === []) {
            return [];
        }

        return $group->matchAll ? [array_merge(...$clauses)] : $clauses;
    }

    /**
     * @return list<string>
     */
    private function clauses(Condition $condition): array
    {
        $subject = $this->catalog->find($condition->subject);
        $operator = Operator::tryFrom($condition->operator);

        if ($subject === null || ! $operator instanceof Operator || ! $subject->allows($operator)) {
            return [];
        }

        return $this->conditions->clauses($subject, $operator, $condition);
    }

    /**
     * @param  list<list<string>>  $alternatives
     */
    private function set(string $name, array $alternatives): string
    {
        $prefix = $name === '' ? '' : "{$name}=";

        return implode(', ', array_map(fn (array $clauses): string => $prefix.'('.implode(', ', $clauses).')', $alternatives));
    }
}
