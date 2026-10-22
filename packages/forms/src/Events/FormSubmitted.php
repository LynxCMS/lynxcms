<?php

declare(strict_types=1);

namespace LynxCms\Forms\Events;

/**
 * A form submission was stored and committed (ADR-0052).
 *
 * @api
 */
final readonly class FormSubmitted
{
    public function __construct(public int $submissionId) {}
}
