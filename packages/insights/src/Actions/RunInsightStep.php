<?php

declare(strict_types=1);

namespace LynxCms\Insights\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Insights\Plans;

/**
 * Runs one step of an insight's plan, nested in insights.apply_step (ADR-0157), so a step that
 * fails or deletes rolls back alone. Nested actions skip the action-level permission check, so
 * this checks the step's permission for the approver first. Not a tool: only the approval's
 * jobs run it.
 *
 * @internal
 */
final class RunInsightStep extends Action
{
    public function __construct(private readonly Plans $plans, private readonly Authorizer $authorizer) {}

    public static function name(): string
    {
        return 'insights.run_step';
    }

    public static function description(): string
    {
        return 'Run one step of an approved insight\'s plan. Refuses a step that deletes anything.';
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
        return ['insight' => ['required', 'integer'], 'step' => ['required', 'integer', 'min:0']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'insight' => $schema->integer()->description('The id of the insight.')->required(),
            'step' => $schema->integer()->description('The step\'s place in the plan, from 0.')->required(),
        ];
    }

    public function handle(Input $input, ActionContext $context): mixed
    {
        $insight = StoredInsight::query()->findOrFail($input->int('insight'));
        $step = $insight->plan[Value::int($input->int('step'))] ?? throw ValidationException::withMessages(['step' => Value::string(__('lynx-insights::validation.no_step'))]);
        $action = $this->plans->action($step['action']);
        $this->authorizer->authorizeAction($context->actor, $action::permission(), $action::name());
        $value = $context->run($action, $step['arguments']);

        if ($context->changes->isDestructive()) {
            throw ValidationException::withMessages(['insight' => Value::string(__('lynx-insights::validation.destructive'))]);
        }

        return $value;
    }
}
