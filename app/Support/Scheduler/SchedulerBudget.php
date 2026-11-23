<?php

declare(strict_types=1);

namespace App\Support\Scheduler;

/**
 * One deadline for a whole scheduler run. The endpoint sets it before `schedule:run`, which starts each task as its own
 * process; they inherit the variable, so each task cuts its own time budget to what is left of the run. Tasks run in
 * order of importance, so a run that is out of time only skips the least important work until the next call.
 *
 * Without a deadline (a manual run from a laptop) every task keeps its configured budget.
 */
final class SchedulerBudget
{
    public const string VARIABLE = 'SCHEDULER_RUN_DEADLINE';

    public static function start(int $seconds): void
    {
        putenv(self::VARIABLE . '=' . (time() + max(1, $seconds)));
    }

    public static function clear(): void
    {
        putenv(self::VARIABLE);
    }

    /** Seconds left in this run, or null outside a budgeted run. */
    public static function secondsLeft(): ?int
    {
        $deadline = getenv(self::VARIABLE);

        return is_string($deadline) && ctype_digit($deadline) ? max(0, (int) $deadline - time()) : null;
    }

    /** $seconds, or less when the run has less left. */
    public static function clamp(int $seconds): int
    {
        $left = self::secondsLeft();

        return $left === null ? $seconds : max(0, min($seconds, $left));
    }

    /** Whether something that may take up to $seconds still fits in this run. */
    public static function allows(int $seconds): bool
    {
        $left = self::secondsLeft();

        return $left === null || $left >= $seconds;
    }
}
