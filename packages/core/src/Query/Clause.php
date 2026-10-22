<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

/**
 * One "field operator value(s)" part of a selector. Several values mean OR.
 *
 * @api
 */
final readonly class Clause
{
    public const array OPERATORS = ['!=', '<=', '>=', '%=', '*=', '~=', '^=', '$=', '=', '<', '>'];

    /**
     * @param  list<string>  $values
     */
    public function __construct(
        public string $field,
        public string $operator,
        public array $values,
    ) {}

    public function value(): string
    {
        return $this->values[0] ?? '';
    }
}
