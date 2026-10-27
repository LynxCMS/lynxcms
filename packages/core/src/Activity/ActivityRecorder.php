<?php

declare(strict_types=1);

namespace LynxCms\Core\Activity;

use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Change;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;

/**
 * Global after-hook: one activity row per outer action (or per page it changed, for actions that
 * ask), never for dry runs.
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

        $changes = $context->changes->all();
        $groups = is_subclass_of($action, Action::class) && $action::logsActivityPerPage() ? $this->byPage($changes) : [$changes];

        foreach ($groups as $group) {
            $this->write(Value::string($action::name()), $group, $input, $context);
        }
    }

    /**
     * Splits the changes at each change to another page, so every page an action changed gets an
     * event of its own (ADR-0091). Changes to other subjects stay with the page before them. A run
     * that changed nothing logs nothing, so the scheduler's empty minutes leave no rows.
     *
     * @param  list<Change>  $changes
     * @return list<list<Change>>
     */
    private function byPage(array $changes): array
    {
        $groups = [];
        $page = null;

        foreach ($changes as $change) {
            if ($groups === [] || ($change->subject === 'page' && $change->id !== $page)) {
                $groups[] = [];
                $page = $change->subject === 'page' ? $change->id : $page;
            }

            $groups[array_key_last($groups)][] = $change;
        }

        return $groups;
    }

    /**
     * One event, filed under its first change's subject.
     *
     * @param  list<Change>  $changes
     */
    private function write(string $action, array $changes, Input $input, ActionContext $context): void
    {
        $first = $changes[0] ?? null;

        ActivityEvent::query()->create([
            'action' => $action,
            'subject_type' => $first?->subject,
            'subject_id' => $first?->id === null ? null : (string) $first->id,
            'language_id' => $input->int('language_id'),
            'actor_id' => $context->actor->id === null ? null : (string) $context->actor->id,
            'actor_kind' => $context->actor->kind,
            'changes' => array_map(fn (Change $change): array => $change->toArray(), $changes),
            'meta' => $context->actor->meta ?: null,
        ]);
    }
}
