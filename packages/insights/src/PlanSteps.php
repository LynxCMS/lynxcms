<?php

declare(strict_types=1);

namespace LynxCms\Insights;

/**
 * The outcome of each step of an approved plan (ADR-0157), kept on the insight in the plan's
 * order: pending until its job ran, then done, or failed with its error.
 *
 * @phpstan-type Step array{status: string, error?: string}
 *
 * @internal
 */
final class PlanSteps
{
    public const string PENDING = 'pending';

    public const string DONE = 'done';

    public const string FAILED = 'failed';

    /**
     * The steps of a new approval: those done before stay done, the others run again.
     *
     * @param  list<Step>|null  $steps
     * @return list<Step>
     */
    public static function forApproval(?array $steps, int $count): array
    {
        return array_map(fn (int $index): array => ($steps[$index]['status'] ?? null) === self::DONE ? ['status' => self::DONE] : ['status' => self::PENDING], range(0, $count - 1));
    }

    /**
     * @param  list<Step>  $steps
     * @return list<int> the indexes of the steps still to run
     */
    public static function pending(array $steps): array
    {
        return array_keys(array_filter($steps, fn (array $step): bool => $step['status'] === self::PENDING));
    }

    /** @param  list<Step>  $steps */
    public static function count(array $steps, string $status): int
    {
        return count(array_filter($steps, fn (array $step): bool => $step['status'] === $status));
    }
}
