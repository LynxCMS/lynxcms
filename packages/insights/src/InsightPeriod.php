<?php

declare(strict_types=1);

namespace LynxCms\Insights;

use Carbon\CarbonImmutable;

/**
 * The days a report looks back over, ending when it is written.
 *
 * @api
 */
final readonly class InsightPeriod
{
    public function __construct(public CarbonImmutable $start, public CarbonImmutable $end) {}

    public static function lastDays(int $days): self
    {
        $end = CarbonImmutable::now();

        return new self($end->subDays(max(1, $days)), $end);
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end);
    }
}
