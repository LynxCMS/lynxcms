<?php

declare(strict_types=1);

namespace LynxCms\Core\Activity;

use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;

/**
 * Global after-hook: one activity row per outer action, never for dry runs.
 *
 * @internal
 */
final class ActivityRecorder
{
    public function __invoke(mixed $result, Input $input, ActionContext $context): void
    {
        if ($context->dryRun || $context->isNested()) {
            return;
        }

        $action = $context->action;

        if (is_subclass_of($action, Action::class) && ! $action::logsActivity()) {
            return;
        }

        $first = $context->changes->all()[0] ?? null;

        ActivityEvent::query()->create([
            'action' => $action::name(),
            'subject_type' => $first?->subject,
            'subject_id' => $first?->id === null ? null : (string) $first->id,
            'language_id' => $input->int('language_id'),
            'actor_id' => $context->actor->id === null ? null : (string) $context->actor->id,
            'actor_kind' => $context->actor->kind,
            'changes' => $context->changes->toArray(),
            'meta' => $context->actor->meta ?: null,
        ]);
    }
}
