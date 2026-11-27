<?php

declare(strict_types=1);

namespace LynxCms\Insights;

/**
 * Fired once a report is stored, after its transaction commits; never on a dry run. A listener can
 * tell reviewers, for example by email.
 *
 * @api
 */
final readonly class InsightsReported
{
    public function __construct(public int $report) {}
}
