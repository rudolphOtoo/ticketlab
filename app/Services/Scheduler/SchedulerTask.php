<?php

declare(strict_types=1);

namespace App\Services\Scheduler;

use Carbon\CarbonImmutable;

/**
 * One kind of scheduled work, run in-process by SchedulerRunner.
 *
 * Every task is idempotent and safe to re-run: it picks its own work from the database each time, skips suspended and
 * archived organisations, and leaves what it could not finish for the next call.
 */
interface SchedulerTask
{
    /** The key used in config/scheduler.php and in the JSON response. */
    public function name(): string;

    /** Whether there is anything to do now: one cheap, indexed EXISTS query. */
    public function isDue(): bool;

    /** Works until done or $deadline; an item already started may run over by the task's overrun_seconds. */
    public function run(CarbonImmutable $deadline): TaskResult;
}
