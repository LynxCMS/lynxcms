<?php

declare(strict_types=1);

namespace LynxCms\Insights\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Insights\Actions\ApplyInsightStep;
use Throwable;

/**
 * One step of an approved plan, on the queue (ADR-0157): runs insights.apply_step as the editor
 * who approved, so their permissions apply to the step. An error from outside Lynx, such as a
 * provider that cannot be reached, is tried again after 30 seconds, then after 2 minutes; the last
 * try keeps the error on the step. When the job gives up for any other reason (the worker's
 * timeout, a lost database connection), failed() marks the step failed, so the insight does not
 * stay running.
 *
 * @internal
 */
final class ApplyStep implements ShouldQueue
{
    use Batchable;
    use Queueable;

    /** The tries before a step that keeps failing for a reason outside Lynx counts as failed. */
    public int $tries = 3;

    public function __construct(public readonly int $insight, public readonly int $step, public readonly Actor $actor) {}

    /** @return list<int> seconds to wait before each new try */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled() === true) {
            return;
        }

        try {
            // The sync queue never tries again, so its one try keeps the error.
            $retry = ! $this->job instanceof SyncJob && $this->attempts() < $this->tries;
            $input = ['insight' => $this->insight, 'step' => $this->step, 'retry' => $retry];
            lynx()->actingAs($this->actor, fn (): ActionResult => lynx()->run(ApplyInsightStep::class, $input));
        } catch (ValidationException) {
            // The step already ran: a retried job, or a newer approval of the same insight.
            return;
        }
    }

    /** The job gave up: the step is marked failed by the system, since it only records the outcome. */
    public function failed(Throwable $exception): void
    {
        $input = ['insight' => $this->insight, 'step' => $this->step, 'failure' => Str::limit($exception->getMessage(), 2000)];

        try {
            lynx()->actingAs(Actor::system(), fn (): ActionResult => lynx()->run(ApplyInsightStep::class, $input));
        } catch (ValidationException) {
            // The step was decided already.
            return;
        }
    }
}
