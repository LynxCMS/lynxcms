<?php

declare(strict_types=1);

namespace LynxCms\Insights\Sources;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use LynxCms\Contracts\Metrics\PageMetrics;
use LynxCms\Core\Support\Value;

/**
 * The site's page metrics, when sources may rest on them (ADR-0156): bound, and with data reaching
 * back lynx-insights.min_days (14). Otherwise null, and the sources rank by links or skip.
 *
 * @internal
 */
final readonly class Metrics
{
    public function __construct(private Container $container) {}

    public function available(): ?PageMetrics
    {
        if (! $this->container->bound(PageMetrics::class)) {
            return null;
        }

        $metrics = $this->container->make(PageMetrics::class);
        $since = $metrics instanceof PageMetrics ? $metrics->since() : null;
        $needed = Carbon::now()->subDays(Value::int(config('lynx-insights.min_days'), 14));

        return $since !== null && $since <= $needed ? $metrics : null;
    }
}
