<?php

declare(strict_types=1);

namespace LynxCms\Insights;

use Illuminate\Support\Str;

/**
 * The outcome of each step of an approved plan (ADR-0157), kept on the insight in the plan's
 * order: pending until its job ran, then done, or failed with its error; stopped when an editor
 * stopped the approval before it ran.
 *
 * A failed step also keeps the exception's class, its trace (cut to TRACE_LIMIT bytes, shown only to
 * superusers), when it failed and after how many tries.
 *
 * @phpstan-type Step array{status: string, error?: string, exception?: string, trace?: string, failed_at?: string, tries?: int}
 *
 * @internal
 */
final class PlanSteps
{
    public const string PENDING = 'pending';

    public const string DONE = 'done';

    public const string FAILED = 'failed';

    public const string STOPPED = 'stopped';

    /** The bytes of a stack trace a failed step keeps; the whole trace is in the site's log. */
    public const int TRACE_LIMIT = 8000;

    /** @return Step a failed step, from its error and where it came from */
    public static function failure(string $error, string $exception, string $trace, int $tries): array
    {
        return [
            'status' => self::FAILED,
            'error' => $error,
            'exception' => $exception,
            'trace' => Str::limit($trace, self::TRACE_LIMIT),
            'failed_at' => now()->toIso8601String(),
            'tries' => max(1, $tries),
        ];
    }

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
