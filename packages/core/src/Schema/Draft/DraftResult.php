<?php

declare(strict_types=1);

namespace LynxCms\Core\Schema\Draft;

use LynxCms\Core\Schema\SchemaSyncResult;

/**
 * A built draft (ADR-0116): the plan it followed, what schema sync reported, and notes for the
 * developer, such as starting a schema folder.
 *
 * @api
 */
final readonly class DraftResult
{
    /**
     * @param  list<string>  $notes
     */
    public function __construct(
        public DraftPlan $plan,
        public SchemaSyncResult $sync,
        public array $notes = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->sync->hasErrors();
    }
}
