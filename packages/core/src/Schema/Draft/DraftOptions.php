<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

/**
 * How a draft is built (ADR-0116). Without files, only the database changes: no views, schema
 * files or types are written, as when an agent builds a draft through the schema.draft action.
 * A draft that leaves fields out of a template the site has is refused, unless pruning is asked:
 * the template's field list is replaced, so those fields would leave it.
 *
 * @api
 */
final readonly class DraftOptions
{
    public function __construct(
        public bool $dryRun = false,
        public bool $views = true,
        public bool $forceViews = false,
        public bool $writeFiles = true,
        public bool $pruneFields = false,
        public bool $pages = true,
    ) {}

    public function asDryRun(): self
    {
        return new self(true, $this->views, $this->forceViews, $this->writeFiles, $this->pruneFields, $this->pages);
    }
}
