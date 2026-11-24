<?php

declare(strict_types=1);

namespace App\Services\Scheduler;

use App\Models\SchedulerRun;
use App\Services\Scheduler\Tasks\CleanUp;
use App\Services\Scheduler\Tasks\RecheckPayoutSubaccounts;
use App\Services\Scheduler\Tasks\ReconcilePendingPayments;
use App\Services\Scheduler\Tasks\ReleaseExpiredHolds;
use App\Services\Scheduler\Tasks\SendOwedTicketEmails;
use App\Services\Scheduler\Tasks\WorkQueues;
use App\Support\Scheduler\SchedulerBudget;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * One scheduler call (cron-job.org → /api/run-scheduler, or `php artisan scheduler:tick`), in this process.
 *
 *  - Idle: every task's EXISTS check is false → "idle", nothing else runs (no lock, no worker, no process).
 *  - Busy: one run lock in the shared store (it expires on its own shortly after the function limit, so a killed
 *    run never blocks the next one for long); a second concurrent call answers "locked" at once.
 *  - Tasks run most important first inside one deadline (config/scheduler.php). A task that throws is logged and
 *    counted; the others still run.
 *  - Every call is recorded in scheduler_runs (SchedulerRunLog), shown on the platform health panel.
 */
final class SchedulerRunner
{
    public const string LOCK = 'scheduler:run';

    /** Most important first. */
    public const array TASKS = [
        ReleaseExpiredHolds::class,
        ReconcilePendingPayments::class,
        SendOwedTicketEmails::class,
        WorkQueues::class,
        RecheckPayoutSubaccounts::class,
        CleanUp::class,
    ];

    /** @var list<SchedulerTask> */
    private readonly array $tasks;

    private readonly SchedulerRunLog $log;

    /**
     * @param list<SchedulerTask>|null $tasks the default list (TASKS) unless a test passes its own
     */
    public function __construct(?array $tasks = null, ?SchedulerRunLog $log = null)
    {
        $this->tasks = $tasks ?? array_map(static fn(string $class): SchedulerTask => app($class), self::TASKS);
        $this->log = $log ?? new SchedulerRunLog();
    }

    /**
     * @return array{
     *     status: 'idle'|'ran'|'locked',
     *     ran: list<string>,
     *     skipped: list<string>,
     *     counts: array<string, array<string, int>>,
     *     errors: int,
     *     error_messages: list<string>,
     *     duration_ms: int,
     *     next_due: list<string>,
     * }
     */
    public function run(): array
    {
        $started = hrtime(true);
        $startedAt = CarbonImmutable::now();
        $due = $this->due();

        if ($due === []) {
            return $this->recorded(null, $startedAt, self::report('idle', $started));
        }

        $lock = $this->lockStore()->lock(self::LOCK, (int) config('scheduler.function_max_duration') + 5);

        if (!$lock->get()) {
            return $this->recorded(null, $startedAt, self::report('locked', $started, ['next_due' => $due]));
        }

        $row = $this->log->start($startedAt);

        $budget = max(1, (int) config('scheduler.run_budget_seconds'));
        $deadline = CarbonImmutable::now()->addSeconds($budget);
        // Tells code shared with web requests (TicketEmailer::sendSoon) that it runs inside a scheduler run.
        SchedulerBudget::start($budget);

        $ran = [];
        $skipped = [];
        $counts = [];
        $errors = [];

        try {
            foreach ($this->tasks as $task) {
                $name = $task->name();

                // Asked again here: an earlier task may have made work for this one (reconcile → owed ticket emails).
                if (!$task->isDue()) {
                    continue;
                }

                $seconds = self::taskBudget($name, $deadline);

                if ($seconds < 1) {
                    $skipped[] = $name;

                    continue;
                }

                $result = $this->runTask($task, CarbonImmutable::now()->addSeconds($seconds));
                $ran[] = $name;
                $counts[$name] = $result->counts;
                array_push($errors, ...array_map(
                    static fn(string $error): string => "{$name}: {$error}",
                    $result->errors,
                ));
            }
        } finally {
            SchedulerBudget::clear();
            $lock->release();
        }

        return $this->recorded($row, $startedAt, self::report('ran', $started, [
            'ran' => $ran,
            'skipped' => $skipped,
            'counts' => $counts,
            'errors' => count($errors),
            'error_messages' => $errors,
            'next_due' => $this->due(),
        ]));
    }

    /**
     * Names of the tasks with work now.
     *
     * @return list<string>
     */
    public function due(): array
    {
        return array_values(array_map(static fn(SchedulerTask $task): string => $task->name(), array_filter(
            $this->tasks,
            static fn(SchedulerTask $task): bool => $task->isDue(),
        )));
    }

    /**
     * Seconds the task may keep starting new items: its own budget, cut so its last item still ends by the deadline.
     */
    public static function taskBudget(string $name, CarbonImmutable $deadline): int
    {
        $left = (int) floor(CarbonImmutable::now()->diffInSeconds($deadline, false));

        return min(
            (int) config("scheduler.{$name}.time_budget_seconds"),
            $left - (int) config("scheduler.{$name}.overrun_seconds"),
        );
    }

    /**
     * @param array{
     *     status: 'idle'|'ran'|'locked',
     *     ran: list<string>,
     *     skipped: list<string>,
     *     counts: array<string, array<string, int>>,
     *     errors: int,
     *     error_messages: list<string>,
     *     duration_ms: int,
     *     next_due: list<string>,
     * } $report
     *
     * @return array{
     *     status: 'idle'|'ran'|'locked',
     *     ran: list<string>,
     *     skipped: list<string>,
     *     counts: array<string, array<string, int>>,
     *     errors: int,
     *     error_messages: list<string>,
     *     duration_ms: int,
     *     next_due: list<string>,
     * }
     */
    private function recorded(?SchedulerRun $row, CarbonImmutable $startedAt, array $report): array
    {
        $this->log->finish($row, $startedAt, $report);

        return $report;
    }

    private function runTask(SchedulerTask $task, CarbonImmutable $deadline): TaskResult
    {
        try {
            return $task->run($deadline);
        } catch (Throwable $exception) {
            Log::error('A scheduled task failed; the other tasks still ran.', [
                'task' => $task->name(),
                'error' => $exception->getMessage(),
            ]);

            $result = new TaskResult();
            $result->error($exception->getMessage());

            return $result;
        }
    }

    private function lockStore(): LockProvider
    {
        $store = Cache::store((string) config('scheduler.lock_store'))->getStore();

        if (!$store instanceof LockProvider) {
            throw new RuntimeException('SCHEDULER_LOCK_STORE must support locks: use database or redis.');
        }

        return $store;
    }

    /**
     * @param 'idle'|'ran'|'locked' $status
     * @param array{
     *     ran?: list<string>,
     *     skipped?: list<string>,
     *     counts?: array<string, array<string, int>>,
     *     errors?: int,
     *     error_messages?: list<string>,
     *     next_due?: list<string>,
     * } $parts
     *
     * @return array{
     *     status: 'idle'|'ran'|'locked',
     *     ran: list<string>,
     *     skipped: list<string>,
     *     counts: array<string, array<string, int>>,
     *     errors: int,
     *     error_messages: list<string>,
     *     duration_ms: int,
     *     next_due: list<string>,
     * }
     */
    private static function report(string $status, int|float $started, array $parts = []): array
    {
        return [
            'status' => $status,
            'ran' => $parts['ran'] ?? [],
            'skipped' => $parts['skipped'] ?? [],
            'counts' => $parts['counts'] ?? [],
            'errors' => $parts['errors'] ?? 0,
            'error_messages' => $parts['error_messages'] ?? [],
            'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'next_due' => $parts['next_due'] ?? [],
        ];
    }
}
