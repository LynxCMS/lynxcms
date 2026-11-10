<?php

declare(strict_types=1);

namespace LynxCms\Finder\Search;

use LynxCms\Core\Support\Value;

/**
 * A card of conditions in the finder: a page matches it when it matches all of them, or any.
 *
 * @api
 */
final readonly class ConditionGroup
{
    /**
     * @param  list<Condition>  $conditions
     */
    public function __construct(public bool $matchAll = true, public array $conditions = []) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $conditions = array_map(fn (mixed $condition): Condition => Condition::fromArray(Value::array($condition)), Value::list($data['conditions'] ?? []));

        return new self(FinderQuery::matchesAll($data['match'] ?? null), $conditions);
    }

    /**
     * @return array{match: string, conditions: list<array{subject: string, operator: string, value: string, to: string, pages: list<int>}>}
     */
    public function toArray(): array
    {
        return ['match' => $this->matchAll ? FinderQuery::ALL : FinderQuery::ANY, 'conditions' => array_map(fn (Condition $condition): array => $condition->toArray(), $this->conditions)];
    }
}
