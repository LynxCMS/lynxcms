<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

/**
 * One set of OR groups in a selector (ADR-0110): a page matches the set when it matches every clause
 * of at least one alternative. "(a=1), (b=2)" is one unnamed set; "x=(a=1), x=(b=2)" a set named x.
 *
 * @api
 */
final readonly class OrGroupSet
{
    /**
     * @param  string  $name  the set's name; "" for the unnamed set
     * @param  list<list<Clause>>  $alternatives
     */
    public function __construct(
        public string $name,
        public array $alternatives,
    ) {}
}
