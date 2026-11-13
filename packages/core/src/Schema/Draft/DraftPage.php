<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

/**
 * A page a draft creates (ADR-0116): where it goes, its template, its title and the values the
 * draft gives; every other field stays empty.
 *
 * @api
 */
final readonly class DraftPage
{
    /**
     * @param  array<string, mixed>  $values  field name => value, title not included
     */
    public function __construct(
        public string $path,
        public string $parent,
        public string $name,
        public string $template,
        public string $title,
        public array $values = [],
    ) {}
}
