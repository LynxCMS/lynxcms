<?php

declare(strict_types=1);

namespace LynxCms\Tests\Fixtures\Insights;

use DateTimeImmutable;
use DateTimeInterface;
use LynxCms\Contracts\Metrics\PageMetrics;

/** Fixed view counts per page, with data since a given day. */
final class FakePageMetrics implements PageMetrics
{
    /**
     * @param  array<int, int>  $views  page id => views
     */
    public function __construct(public array $views = [], public ?DateTimeInterface $since = new DateTimeImmutable('-60 days')) {}

    public function totals(array $pageIds, string $event, int $days): array
    {
        return array_intersect_key($this->views, array_flip($pageIds));
    }

    public function top(string $event, int $days, int $limit): array
    {
        $views = $this->views;
        arsort($views);

        return array_slice($views, 0, $limit, preserve_keys: true);
    }

    public function since(): ?DateTimeInterface
    {
        return $this->since;
    }
}
