<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

/**
 * A page a draft creates (ADR-0116): where it goes, its template and the values the draft gives,
 * the title among them when the template has one; every other field stays empty.
 *
 * @api
 */
final readonly class DraftPage
{
    /**
     * @param  array<string, mixed>  $values  field name => value
     */
    public function __construct(
        public string $path,
        public string $parent,
        public string $name,
        public string $template,
        public array $values = [],
    ) {}
}
