<?php

declare(strict_types=1);

namespace LynxCms\Ai;

use LynxCms\Contracts\Actions\Actor;

/**
 * One run of the built-in agent: who it acts for and every action it performed, in order.
 * In plan mode those actions run inside a transaction that is rolled back; the recorded steps
 * are then replayed exactly when the user approves (ADR-0020).
 */
final class AgentSession
{
    /** @var list<array{action: class-string, tool: string, arguments: array<string, mixed>, changes: list<string>, destructive: bool, page_id: int|null}> */
    private array $steps = [];

    public function __construct(public readonly Actor $actor) {}

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
