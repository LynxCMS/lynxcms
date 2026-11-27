<?php

declare(strict_types=1);

namespace LynxCms\Insights\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Insights\Actions\ApplyInsightStep;

/**
 * One step of an approved plan, on the queue (ADR-0157): runs insights.apply_step as the editor
 * who approved, so their permissions apply to the step.
 *
 * @internal
 */
final class ApplyStep implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public function __construct(public readonly int $insight, public readonly int $step, public readonly Actor $actor) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled() === true) {
            return;
        }

        try {
            lynx()->actingAs($this->actor, fn (): ActionResult => lynx()->run(ApplyInsightStep::class, ['insight' => $this->insight, 'step' => $this->step]));
        } catch (ValidationException) {
            // The step already ran: a retried job, or a newer approval of the same insight.
            return;
        }
    }
}
