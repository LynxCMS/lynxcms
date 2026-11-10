<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

/**
 * A parsed selector: its clauses, every one of which must match, and its sets of OR groups, every
 * one of which must match through at least one of its alternatives (ADR-0110).
 *
 * @api
 */
final readonly class ParsedSelector
{
    /**
     * @param  list<Clause>  $clauses
     * @param  list<OrGroupSet>  $groups
     */
    public function __construct(
        public array $clauses,
        public array $groups = [],
    ) {}
}
