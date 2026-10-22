<?php

declare(strict_types=1);

namespace LynxCms\Ai;

use Illuminate\Support\Facades\DB;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Pages\Page;

/**
 * The result of $lynx->ai()->ask(): the agent's answer and the plan of recorded actions.
 * If the run was not applied, apply() replays the exact recorded actions, without asking the
 * model again, so what the user approved is what runs.
 */
final class AgentRun
{
    /**
     * @param  list<array{action: class-string, tool: string, arguments: array<string, mixed>, changes: list<string>, destructive: bool, page_id: int|null}>  $steps
     */
    public function __construct(
        public readonly string $text,
        public readonly array $steps,
        private bool $applied,
        private readonly AgentSession $session,
    ) {}

    public function isApplied(): bool
    {
        return $this->applied;
    }

    public function isEmpty(): bool
    {
        return $this->steps === [];
    }

    public function isDestructive(): bool
    {
        return array_any($this->steps, fn (array $step): bool => $step['destructive']);
    }

    /**
     * Human-readable plan, one line per change.
     *
     * @return list<string>
     */
    public function plan(): array
    {
        $lines = [];

        foreach ($this->steps as $i => $step) {
            $lines[] = sprintf('%d. %s', $i + 1, $step['tool']);

            foreach ($step['changes'] as $change) {
                $lines[] = '   '.$change;
            }
        }

        return $lines;
    }

    /**
     * Replay the recorded actions in one transaction. Ids of pages created during planning are
     * mapped to the ids created now.
     */
    public function apply(): self
    {
        if ($this->applied) {
            return $this;
        }

        $runner = app(ActionRunner::class);
        $map = [];

        DB::transaction(function () use ($runner, &$map): void {
            $runner->actingAs($this->session->actor, function () use ($runner, &$map): void {
                foreach ($this->steps as $step) {
                    $result = $runner->run($step['action'], $this->remap($step['arguments'], $map));

                    if ($step['page_id'] !== null && $result->value instanceof Page) {
                        $map[$step['page_id']] = (int) $result->value->id;
                    }
                }
            });
        });

        $this->applied = true;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $map
     * @return array<string, mixed>
     */
    private function remap(array $arguments, array $map): array
    {
        if ($map === []) {
            return $arguments;
        }

        foreach (['id', 'page', 'parent'] as $key) {
            if (isset($arguments[$key]) && is_numeric($arguments[$key]) && isset($map[(int) $arguments[$key]])) {
                $arguments[$key] = $map[(int) $arguments[$key]];
            }
        }

        if (isset($arguments['values']) && is_array($arguments['values'])) {
            array_walk_recursive($arguments['values'], function (mixed &$value) use ($map): void {
                if (is_int($value) && isset($map[$value])) {
                    $value = $map[$value];
                }
            });
        }

        return $arguments;
    }
}
