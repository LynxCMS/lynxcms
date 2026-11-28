<?php

declare(strict_types=1);

namespace LynxCms\Insights;

use Carbon\CarbonImmutable;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\Models\StoredInsight;

/**
 * What earlier reports say about an insight: whether an editor dismissed it lately, and which
 * earlier insight it replaces.
 *
 * @internal
 */
final readonly class InsightHistory
{
    /**
     * Dismissed within the quiet days, and its weight has not doubled since.
     */
    public function isQuiet(string $fingerprint, float $weight): bool
    {
        $days = Value::int(config('lynx-insights.quiet_days'), 180);
        $dismissed = StoredInsight::query()
            ->where('fingerprint', $fingerprint)
            ->where('state', InsightState::Dismissed->value)
            ->where('decided_at', '>=', CarbonImmutable::now()->subDays($days))
            ->latest('id')
            ->first();

        return $dismissed instanceof StoredInsight && ($dismissed->weight <= 0 || $weight < 2 * $dismissed->weight);
    }

    /** Running: its approved steps are still running (ADR-0157), so a report leaves it out. */
    public function isRunning(string $fingerprint): bool
    {
        return StoredInsight::query()->where('fingerprint', $fingerprint)->where('state', InsightState::Running->value)->exists();
    }

    /** The latest open or archived insight with this fingerprint in an earlier report. */
    public function replaces(string $fingerprint, int $report): ?int
    {
        $id = StoredInsight::query()
            ->where('fingerprint', $fingerprint)
            ->where('report_id', '!=', $report)
            ->whereIn('state', [InsightState::Open->value, InsightState::Archived->value])
            ->max('id');

        return $id === null ? null : Value::int($id);
    }
}
