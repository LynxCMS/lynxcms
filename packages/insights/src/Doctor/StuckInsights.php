<?php

declare(strict_types=1);

namespace LynxCms\Insights\Doctor;

use Illuminate\Support\Carbon;
use LynxCms\Core\Doctor\Check;
use LynxCms\Core\Doctor\Finding;
use LynxCms\Core\Doctor\Severity;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\StoredInsight;

/**
 * Insights running with no progress for lynx-insights.stuck_minutes (ADR-0157). Every finished
 * step saves the insight, so a long plan that moves is not one; one that stopped moving has jobs
 * nobody runs, most often because no queue worker does, or a worker died mid-step.
 *
 * @internal
 */
final readonly class StuckInsights implements Check
{
    public function key(): string
    {
        return 'insights-stuck';
    }

    public function label(): string
    {
        return 'Approved insights are making progress';
    }

    public function run(): array
    {
        $minutes = Value::int(config('lynx-insights.stuck_minutes'), 30);
        $ids = StoredInsight::query()
            ->where('state', InsightState::Running->value)
            ->where('updated_at', '<', Carbon::now()->subMinutes($minutes))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return [];
        }

        return [new Finding(
            Severity::Warning,
            count($ids)." insight(s) running with no progress for {$minutes} minutes: #".implode(', #', Value::ints($ids)).'.',
            'Check that a queue worker runs (php artisan queue:work) and read failed_jobs, or stop the insight on the Insights screen (insights.stop) and approve it again.',
        )];
    }
}
