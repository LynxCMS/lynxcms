<?php

declare(strict_types=1);

namespace LynxCms\Insights\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\Insight;
use LynxCms\Insights\InsightHistory;
use LynxCms\Insights\InsightPeriod;
use LynxCms\Insights\InsightSource;
use LynxCms\Insights\InsightSourceRegistry;
use LynxCms\Insights\InsightsReported;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\InsightReport;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Insights\PageBasis;
use LynxCms\Insights\Plans;
use Throwable;

/**
 * Writes a report (ADR-0155): every source suggests changes for the period, and the insights are
 * stored in one report. Insights dismissed lately stay out; an insight reported again replaces the
 * open or archived one of an earlier report; open insights of earlier reports are superseded. A
 * source that fails is listed in the report, and the others are kept.
 *
 * @api
 */
final class GenerateInsights extends Action
{
    use ResolvesInsights;

    public function __construct(
        private readonly InsightSourceRegistry $sources,
        private readonly InsightHistory $history,
        private readonly PageBasis $basis,
        private readonly Plans $plans,
    ) {}

    public static function name(): string
    {
        return 'insights.generate';
    }

    public static function description(): string
    {
        return 'Write an insights report: every insight source suggests changes for the period, each with its evidence and a plan to approve. Nothing on the site changes.';
    }

    public static function permission(): string
    {
        return 'insights.review';
    }

    public static function rules(): array
    {
        return ['days' => ['sometimes', 'integer', 'min:1', 'max:366']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['days' => $schema->integer()->description('The days the report looks back over. Default: the configured period, 30 days.')];
    }

    public function handle(Input $input, ActionContext $context): InsightReport
    {
        $period = InsightPeriod::lastDays($input->int('days') ?? Value::int(config('lynx-insights.period_days'), 30));
        $report = InsightReport::query()->create([
            'period_start' => $period->start, 'period_end' => $period->end, 'failures' => [], 'created_by' => self::actorKey($context->actor),
        ]);
        $failures = [];

        foreach ($this->sources->all() as $source) {
            $failures = [...$failures, ...$this->store($report, $source, $period, $context)];
        }

        StoredInsight::query()->where('report_id', '!=', $report->id)->where('state', InsightState::Open->value)
            ->update(['state' => InsightState::Superseded->value]);
        $report->update(['failures' => $failures]);
        $context->changes->created('insight_report', $report->id, 'Write an insights report with '.$report->insights()->count().' insight(s)');
        $context->event(new InsightsReported($report->id));

        return $report;
    }

    /**
     * @return list<array{source: string, error: string}> what failed
     */
    private function store(InsightReport $report, InsightSource $source, InsightPeriod $period, ActionContext $context): array
    {
        try {
            $insights = $source->insights($period);
        } catch (Throwable $e) {
            Log::error("Insight source [{$source->key()}] failed: {$e->getMessage()}", ['exception' => $e]);

            return [['source' => $source->key(), 'error' => $e->getMessage()]];
        }

        $failures = [];

        foreach ($insights as $insight) {
            try {
                $this->storeOne($report, $source->key(), $insight, $context);
            } catch (InvalidArgumentException $e) {
                $failures[] = ['source' => $source->key(), 'error' => $e->getMessage()];
            }
        }

        return $failures;
    }

    private function storeOne(InsightReport $report, string $source, Insight $insight, ActionContext $context): void
    {
        $fingerprint = $insight->fingerprint($source);

        if ($this->history->isQuiet($fingerprint, $insight->weight) || $this->history->isRunning($fingerprint)) {
            return;
        }

        $stored = $report->insights()->create([
            'source' => $source, 'fingerprint' => $fingerprint, 'kind' => $insight->kind,
            'title' => $insight->title, 'reason' => $insight->reason, 'pages' => $insight->pages,
            'evidence' => $insight->evidence, 'plan' => $this->plans->normalize($insight->plan),
            'basis' => $this->basis->of($insight->pages), 'weight' => $insight->weight,
            'state' => InsightState::Open, 'carried_from' => $this->history->replaces($fingerprint, $report->id),
        ]);
        $context->changes->created('insight', $stored->id, "Suggest: {$insight->title}");
    }
}
