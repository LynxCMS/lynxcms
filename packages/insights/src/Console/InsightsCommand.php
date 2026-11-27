<?php

declare(strict_types=1);

namespace LynxCms\Insights\Console;

use Illuminate\Console\Command;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Actions\Runner\Change;
use LynxCms\Insights\Actions\GenerateInsights;
use LynxCms\Insights\Models\InsightReport;
use Override;

/**
 * Writes an insights report now, as the scheduler does, and lists its insights.
 *
 * @internal
 */
final class InsightsCommand extends Command
{
    /** @var string */
    #[Override]
    protected $signature = 'lynx:insights {--days= : The days the report looks back over} {--dry-run : List the insights without storing a report}';

    /** @var string */
    #[Override]
    protected $description = 'Write an insights report: suggested changes for editors to approve';

    public function handle(ActionRunner $runner): int
    {
        $days = $this->option('days');
        $input = is_numeric($days) ? ['days' => (int) $days] : [];
        $result = $runner->run(GenerateInsights::class, $input, dryRun: (bool) $this->option('dry-run'));
        $report = $result->valueAs(InsightReport::class);
        // A dry run rolled the report back: its changes name the insights it would store.
        $titles = array_values(array_filter(array_map(fn (Change $change): ?string => $change->subject === 'insight' ? $change->summary : null, $result->changes->all())));

        $titles === [] ? $this->components->info('Nothing to suggest for this period.') : $this->components->bulletList($titles);

        foreach ($report->failures as $failure) {
            $this->components->warn("Source [{$failure['source']}] failed: {$failure['error']}");
        }

        return self::SUCCESS;
    }
}
