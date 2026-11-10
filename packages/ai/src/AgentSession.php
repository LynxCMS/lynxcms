<?php

declare(strict_types=1);

namespace LynxCms\Ai;

use Carbon\CarbonImmutable;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Support\Value;

/**
 * One run of the built-in agent: who it acts for and every action it performed, in order.
 * In plan mode those actions run inside a transaction that is rolled back; the recorded steps
 * are then replayed exactly when the user approves (ADR-0020). A run has lynx.ai.plan_seconds to
 * finish, since its transaction holds other writers back.
 */
final class AgentSession
{
    /** @var list<array{action: class-string, tool: string, arguments: array<string, mixed>, changes: list<string>, destructive: bool, page_id: int|null}> */
    private array $steps = [];

    private const int DEFAULT_SECONDS = 120;

    private readonly CarbonImmutable $deadline;

    private readonly int $seconds;

    /** Set once the run passed its time, so the run stops even when laravel/ai hands tool errors back to the model. */
    private ?PlanTookTooLong $timedOut = null;

    public function __construct(public readonly Actor $actor)
    {
        $this->seconds = max(1, Value::int(config('lynx.ai.plan_seconds'), self::DEFAULT_SECONDS));
        $this->deadline = CarbonImmutable::now()->addSeconds($this->seconds);
    }

    /**
     * Called before each tool call: the run's transaction stays open until it ends, so a run
     * past its time stops instead of keeping other writers waiting. Every later call fails too.
     *
     * @throws PlanTookTooLong
     */
    public function assertTimeLeft(): void
    {
        if ($this->timedOut === null && CarbonImmutable::now()->greaterThan($this->deadline)) {
            $this->timedOut = PlanTookTooLong::after($this->seconds);
        }

        if ($this->timedOut instanceof PlanTookTooLong) {
            throw $this->timedOut;
        }
    }

    /**
     * Called once the agent has answered: since laravel/ai 1.4 a tool's exception goes back to the
     * model as a failed call instead of ending the run, so a run that timed out is stopped here,
     * and its transaction rolled back.
     *
     * @throws PlanTookTooLong
     */
    public function assertFinishedInTime(): void
    {
        if ($this->timedOut instanceof PlanTookTooLong) {
            throw $this->timedOut;
        }
    }

    /**
     * @param  class-string  $action
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $changes
     */
    public function record(string $action, string $tool, array $arguments, array $changes, bool $destructive, ?int $pageId): void
    {
        $this->steps[] = [
            'action' => $action,
            'tool' => $tool,
            'arguments' => $arguments,
            'changes' => $changes,
            'destructive' => $destructive,
            'page_id' => $pageId,
        ];
    }

    /**
     * @return list<array{action: class-string, tool: string, arguments: array<string, mixed>, changes: list<string>, destructive: bool, page_id: int|null}>
     */
    public function steps(): array
    {
        return $this->steps;
    }
}
