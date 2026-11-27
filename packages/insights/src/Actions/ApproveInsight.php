<?php

declare(strict_types=1);

namespace LynxCms\Insights\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Jobs\ApplyStep;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Insights\PageBasis;
use LynxCms\Insights\Plans;
use LynxCms\Insights\PlanSteps;

/**
 * Makes the change an insight suggests (ADR-0155, ADR-0157), as the person approving. Every step's
 * action needs that person's permission, as if they ran it themselves, and it is refused when a
 * page the insight is about changed since the report. The steps then run on the queue, a job per
 * step, and the insight is approving until the last one ends; a step that deletes anything is
 * refused. A dry run runs the whole plan at once instead, so it lists every change and refuses a
 * plan that deletes. Approving again after a step failed runs only the steps not done.
 *
 * @api
 */
final class ApproveInsight extends Action
{
    use ResolvesInsights;

    public function __construct(
        private readonly PageBasis $basis,
        private readonly Plans $plans,
        private readonly Authorizer $authorizer,
    ) {}

    public static function name(): string
    {
        return 'insights.approve';
    }

    public static function description(): string
    {
        return 'Approve an open insight: its plan runs on the queue, a step at a time, as you, with your permissions. Use a dry run to see what it would change.';
    }

    public static function permission(): string
    {
        return 'insights.review';
    }

    public static function rules(): array
    {
        return self::insightRules();
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['insight' => $schema->integer()->description('The id of the insight.')->required()];
    }

    public function handle(Input $input, ActionContext $context): StoredInsight
    {
        $insight = $this->openInsight($input->get('insight'));
        $this->assertRunnable($insight);
        $steps = array_map(fn (array $step): array => [$this->plans->action($step['action']), $step['arguments']], $insight->plan);

        foreach ($steps as [$action]) {
            $this->authorizer->authorizeAction($context->actor, $action::permission(), $action::name());
        }

        return $context->dryRun ? $this->rehearse($insight, $steps, $context) : $this->queue($insight, $context);
    }

    /**
     * The whole plan at once, rolled back with the dry run: every change listed for review.
     *
     * @param  list<array{0: class-string<Action>, 1: array<string, mixed>}>  $steps
     */
    private function rehearse(StoredInsight $insight, array $steps, ActionContext $context): StoredInsight
    {
        foreach ($steps as [$action, $arguments]) {
            $context->run($action, $arguments);
        }

        if ($context->changes->isDestructive()) {
            throw ValidationException::withMessages(['insight' => Value::string(__('lynx-insights::validation.destructive'))]);
        }

        return $insight;
    }

    private function queue(StoredInsight $insight, ActionContext $context): StoredInsight
    {
        $steps = PlanSteps::forApproval($insight->steps, count($insight->plan));
        $insight->update(['state' => InsightState::Approving, 'steps' => $steps]);
        $context->changes->updated('insight', $insight->id, "Approve insight {$insight->title}: ".count(PlanSteps::pending($steps)).' step(s) queued');
        $jobs = array_map(fn (int $index): ApplyStep => new ApplyStep($insight->id, $index, $context->actor), PlanSteps::pending($steps));
        $name = "Insight {$insight->id}";

        // After the commit: a job must find the insight approving, and the sync queue runs it at once.
        DB::afterCommit(fn () => Bus::batch($jobs)->name($name)->allowFailures()->dispatch());

        return $insight;
    }

    private function assertRunnable(StoredInsight $insight): void
    {
        if ($insight->plan === []) {
            throw ValidationException::withMessages(['insight' => Value::string(__('lynx-insights::validation.no_plan'))]);
        }

        $changed = $this->basis->changedPage($insight->basis);

        if ($changed !== null) {
            $name = lynx()->pages->get($changed)?->name() ?? "#{$changed}";

            throw ValidationException::withMessages(['insight' => Value::string(__('lynx-insights::validation.changed', ['page' => $name]))]);
        }
    }
}
