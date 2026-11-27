<?php

declare(strict_types=1);

namespace LynxCms\Insights\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Insights\PageBasis;
use LynxCms\Insights\PlanSteps;

/**
 * Stops a running approval (ADR-0157): the steps done stay done, the waiting ones are marked
 * stopped, so their jobs do nothing when they run, and the insight is open again with its pages'
 * basis taken afresh. Approving again runs the steps not done. A step already running finishes.
 *
 * @api
 */
final class StopInsight extends Action
{
    use ResolvesInsights;

    public function __construct(private readonly PageBasis $basis) {}

    public static function name(): string
    {
        return 'insights.stop';
    }

    public static function description(): string
    {
        return 'Stop a running insight: the changes made stay, the waiting ones are not made, and the insight is open again. Approving it again makes the rest.';
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
        return ['insight' => $schema->integer()->description('The id of the running insight.')->required()];
    }

    public function handle(Input $input, ActionContext $context): StoredInsight
    {
        // Locked: a step's job may be deciding the insight at the same time.
        $insight = StoredInsight::query()->lockForUpdate()->find(Value::int($input->get('insight')));

        if (! $insight instanceof StoredInsight || $insight->state !== InsightState::Running) {
            throw ValidationException::withMessages(['insight' => Value::string(__('lynx-insights::validation.not_running'))]);
        }

        $steps = array_map(fn (array $step): array => $step['status'] === PlanSteps::PENDING ? ['status' => PlanSteps::STOPPED] : $step, $insight->steps ?? []);
        $insight->update(['state' => InsightState::Open, 'steps' => $steps, 'basis' => $this->basis->of($insight->pages)]);
        $context->changes->updated('insight', $insight->id, "Stop insight {$insight->title}: ".PlanSteps::count($steps, PlanSteps::STOPPED).' step(s) not made');

        return $insight;
    }
}
