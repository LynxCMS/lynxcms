<?php

declare(strict_types=1);

namespace LynxCms\Insights\Actions;

use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Support\Value;
use LynxCms\Insights\InsightState;
use LynxCms\Insights\Models\StoredInsight;

/**
 * Finds the open insight an action decides on, and records the decision.
 *
 * @internal
 */
trait ResolvesInsights
{
    private function openInsight(mixed $id): StoredInsight
    {
        $insight = StoredInsight::query()->find(Value::int($id));

        if (! $insight instanceof StoredInsight) {
            throw ValidationException::withMessages(['insight' => Value::string(__('lynx-insights::validation.missing'))]);
        }

        if ($insight->state !== InsightState::Open) {
            throw ValidationException::withMessages(['insight' => Value::string(__('lynx-insights::validation.closed'))]);
        }

        return $insight;
    }

    private function decide(StoredInsight $insight, InsightState $state, ActionContext $context): StoredInsight
    {
        $insight->update(['state' => $state, 'decided_by' => self::actorKey($context->actor), 'decided_at' => now()]);
        $context->changes->updated('insight', $insight->id, ucfirst($state->value)." insight {$insight->title}");

        return $insight;
    }

    /** How an actor is stored: "user:5". */
    private static function actorKey(Actor $actor): string
    {
        return $actor->kind.($actor->id !== null ? ':'.$actor->id : '');
    }

    /** @return array<string, list<string>> */
    private static function insightRules(): array
    {
        return ['insight' => ['required', 'integer']];
    }
}
