<?php

declare(strict_types=1);

namespace LynxCms\Insights\Actions;

use Illuminate\Auth\Access\AuthorizationException;
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
use Throwable;

/**
 * Runs one pending step of a running insight and keeps its outcome (ADR-0157). The step runs
 * nested in insights.run_step, so when it fails only its own writes roll back and the error is
 * kept. A refused step (validation, permissions) fails at once; any other error, such as a
 * provider that cannot be reached, is thrown while `retry` is true, so the job tries again and the
 * step stays pending. With `failure`, the step is marked failed without running: its job gave up. The step that finishes last decides the insight: approved when every step is done, else
 * open again with its pages' basis taken afresh, so approving again runs what is left. Not a tool:
 * the approval's jobs run it, as the approver.
 *
 * @internal
 */
final class ApplyInsightStep extends Action
{
    use ResolvesInsights;

    public function __construct(private readonly PageBasis $basis) {}

    public static function name(): string
    {
        return 'insights.apply_step';
    }

    public static function description(): string
    {
        return 'Run one pending step of a running insight and keep its outcome.';
    }

    public static function permission(): string
    {
        return 'insights.review';
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return [
            'insight' => ['required', 'integer'],
            'step' => ['required', 'integer', 'min:0'],
            'retry' => ['sometimes', 'boolean'],
            'failure' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'insight' => $schema->integer()->description('The id of the insight.')->required(),
            'step' => $schema->integer()->description('The step\'s place in the plan, from 0.')->required(),
            'retry' => $schema->boolean()->description('Throw an error from outside Lynx instead of failing the step, so the job tries again.'),
            'failure' => $schema->string()->description('Mark the step failed with this error, without running it.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): StoredInsight
    {
        // Locked: the jobs of one approval can finish at once, and only one may decide the insight.
        $insight = StoredInsight::query()->lockForUpdate()->find($input->int('insight'));
        $index = Value::int($input->int('step'));
        $steps = $insight->steps ?? [];

        if (! $insight instanceof StoredInsight || $insight->state !== InsightState::Running || ($steps[$index]['status'] ?? null) !== PlanSteps::PENDING) {
            throw ValidationException::withMessages(['step' => Value::string(__('lynx-insights::validation.step_ran'))]);
        }

        $failure = $input->string('failure');
        $steps[$index] = $failure !== null ? ['status' => PlanSteps::FAILED, 'error' => $failure] : $this->run($insight, $index, $context, $input->bool('retry'));
        $insight->steps = $steps;
        $context->changes->updated('insight', $insight->id, "Run step {$index} of insight {$insight->title}: {$steps[$index]['status']}");

        return PlanSteps::pending($steps) === [] ? $this->finish($insight, $steps, $context) : tap($insight)->save();
    }

    /**
     * @return array{status: string, error?: string}
     */
    private function run(StoredInsight $insight, int $index, ActionContext $context, bool $retry): array
    {
        try {
            $context->run(RunInsightStep::class, ['insight' => $insight->id, 'step' => $index]);

            return ['status' => PlanSteps::DONE];
        } catch (ValidationException $e) {
            return ['status' => PlanSteps::FAILED, 'error' => (string) $e->validator->errors()->first()];
        } catch (AuthorizationException $e) {
            return ['status' => PlanSteps::FAILED, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($retry) {
                throw $e;
            }

            // Kept on the insight for the editor, and reported for whoever reads the logs.
            report($e);
            $error = $e->getMessage();

            return ['status' => PlanSteps::FAILED, 'error' => $error];
        }
    }

    /**
     * @param  list<array{status: string, error?: string}>  $steps
     */
    private function finish(StoredInsight $insight, array $steps, ActionContext $context): StoredInsight
    {
        if (PlanSteps::count($steps, PlanSteps::DONE) === count($steps)) {
            return $this->decide($insight, InsightState::Approved, $context);
        }

        $insight->update(['state' => InsightState::Open, 'basis' => $this->basis->of($insight->pages)]);

        return $insight;
    }
}
