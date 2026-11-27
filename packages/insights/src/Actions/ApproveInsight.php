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
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\StoredInsight;
use LynxCms\Insights\PageBasis;
use LynxCms\Insights\Plans;

/**
 * Makes the change an insight suggests (ADR-0155): its whole plan, in order, as the person
 * approving. Every step's action needs that person's permission, as if they ran it themselves.
 * Refused when a page the insight is about changed since the report, and when the plan would
 * delete anything. A step that fails undoes the others.
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
        return 'Approve an open insight: run its whole plan, as you, with your permissions. Use a dry run to see what it would change.';
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

        foreach ($steps as [$action, $arguments]) {
            $context->run($action, $arguments);
        }

        if ($context->changes->isDestructive()) {
            throw ValidationException::withMessages(['insight' => Value::string(__('lynx-insights::validation.destructive'))]);
        }

        return $this->decide($insight, InsightState::Approved, $context);
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
