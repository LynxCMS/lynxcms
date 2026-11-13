<?php

declare(strict_types=1);

namespace LynxCms\Finder\Search;

use LynxCms\Core\Support\Value;

/**
 * A finder search: groups of conditions, combined so a page matches all of them or any, the order
 * of the results and the fields shown as columns. It travels as an array: in the screen's URL and
 * in saved searches.
 *
 * @api
 */
final readonly class FinderQuery
{
    public const string ALL = 'all';

    public const string ANY = 'any';

    /** Orders the results may take, as selector sort values; the first is the default. */
    public const array SORTS = ['-modified', 'modified', 'title', '-title', '-created', '-published'];

    /** The most fields a search shows as columns. */
    public const int MAX_COLUMNS = 6;

    /**
     * @param  list<ConditionGroup>  $groups
     * @param  list<string>  $columns  field names, each once
     */
    public function __construct(public bool $matchAll = true, public array $groups = [], public string $sort = self::SORTS[0], public array $columns = []) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $groups = array_map(fn (mixed $group): ConditionGroup => ConditionGroup::fromArray(Value::array($group)), Value::list($data['groups'] ?? []));
        $sort = Value::string($data['sort'] ?? '');

        $columns = array_slice(array_values(array_unique(Value::strings($data['columns'] ?? []))), 0, self::MAX_COLUMNS);

        return new self(self::matchesAll($data['match'] ?? null), $groups, in_array($sort, self::SORTS, true) ? $sort : self::SORTS[0], $columns);
    }

    /**
     * The same search with other groups.
     *
     * @param  list<ConditionGroup>  $groups
     */
    public function withGroups(array $groups): self
    {
        return new self($this->matchAll, $groups, $this->sort, $this->columns);
    }

    /** "any" means any; everything else, missing included, means all. */
    public static function matchesAll(mixed $match): bool
    {
        return $match !== self::ANY;
    }

    /**
     * @return array{match: string, groups: list<array{match: string, conditions: list<array{subject: string, operator: string, value: string, to: string, pages: list<int>}>}>, sort: string, columns: list<string>}
     */
    public function toArray(): array
    {
        return ['match' => $this->matchAll ? self::ALL : self::ANY, 'groups' => array_map(fn (ConditionGroup $group): array => $group->toArray(), $this->groups), 'sort' => $this->sort, 'columns' => $this->columns];
    }
}
