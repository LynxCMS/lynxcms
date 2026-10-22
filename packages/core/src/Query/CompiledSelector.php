<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Illuminate\Database\Query\Builder;

/**
 * @internal
 */
final readonly class CompiledSelector
{
    public function __construct(
        public Builder $query,
        public Builder $countQuery,
        public ?int $limit,
        public int $start,
        /** @var list<string> fields to load up front (join=) */
        public array $join = [],
    ) {}

    public function toSql(): string
    {
        return $this->query->toRawSql();
    }
}
