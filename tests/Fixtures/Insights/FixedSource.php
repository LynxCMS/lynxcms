<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures\Insights;

use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\InsightSource;
use RuntimeException;

/** Reports the insights it was given, or throws when given none and told to fail. */
final class FixedSource implements InsightSource
{
    public ?InsightPeriod $period = null;

    /**
     * @param  list<Insight>  $insights
     */
    public function __construct(private readonly string $key, public array $insights = [], private readonly bool $fails = false) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return ucfirst($this->key);
    }

    public function insights(InsightPeriod $period): array
    {
        if ($this->fails) {
            throw new RuntimeException("The {$this->key} source broke.");
        }

        $this->period = $period;

        return $this->insights;
    }
}
