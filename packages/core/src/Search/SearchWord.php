<?php

declare(strict_types=1);

namespace LynxCms\Core\Search;

/**
 * One word of a search (ADR-0119): matched as the beginning of an indexed word, or, when the
 * language reduces it, as its reduced form exactly. Both hold only letters and digits.
 *
 * @internal
 */
final readonly class SearchWord
{
    public function __construct(
        public string $prefix,
        public ?string $reduced = null,
    ) {}
}
