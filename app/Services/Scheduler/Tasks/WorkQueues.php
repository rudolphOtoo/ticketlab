<?php

declare(strict_types=1);

namespace App\Services\Scheduler\Tasks;

use App\Services\Scheduler\SchedulerTask;
use App\Services\Scheduler\TaskResult;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * The serverless queue worker: takes queued jobs one at a time in this process (Worker::runNextJob), stopping when
 * the queues are empty, after max_jobs, or at the deadline. No `queue:work` process, no signal handlers: a job that
 * outlives the request is retried once its reservation expires (retry_after). Every queue jobs are dispatched onto
 * is worked (config queue.worker_queues, most urgent first).
 */
final class WorkQueues implements SchedulerTask
{
    /** Only this task's own jobs are counted (the listeners stay registered for the process). */
    private bool $active = false;

    private TaskResult $result;

    private bool $listening = false;

    public function __construct()
    {
        $this->result = new TaskResult();
    }

    public function name(): string
    {
        return 'worker';
    }

    /** Jobs a worker could take now, including ones whose worker was killed and whose reservation expired. */
    public function isDue(): bool
    {
        $connection = (string) config('queue.default');

        if (config("queue.connections.{$connection}.driver") !== 'database') {
            return $connection !== 'sync'; // Another backend: let the worker look.
        }

        $table = (string) config("queue.connections.{$connection}.table", 'jobs');
        $retryAfter = (int) config("queue.connections.{$connection}.retry_after", 90);

        return DB::table($table)
            ->whereIn('queue', (array) config('queue.worker_queues'))
            ->where(static fn($query) => $query->where(static fn($available) => $available->whereNull(
                'reserved_at',
            )->where('available_at', '<=', now()->getTimestamp()))->orWhere(
                'reserved_at',
                '<=',
                now()->subSeconds($retryAfter)->getTimestamp(),
            ))
            ->exists();
    }

    public function run(CarbonImmutable $deadline): TaskResult
    {
        $this->result = new TaskResult(['processed' => 0, 'failed' => 0]);
        $this->listen();

        $worker = app('queue.worker');
        $connection = (string) config('queue.default');
        $queues = implode(',', (array) config('queue.worker_queues'));
        $maxJobs = max(1, (int) config('scheduler.worker.max_jobs'));
        $options = new WorkerOptions(name: 'scheduler', sleep: 0, maxTries: 1, stopWhenEmpty: true, maxJobs: $maxJobs);

        $this->active = true;

        try {
            while ($this->jobsTaken() < $maxJobs && CarbonImmutable::now()->lessThan($deadline)) {
                $before = $this->jobsTaken();
                $worker->runNextJob($connection, $queues, $options);

                if ($this->jobsTaken() === $before) {
                    break; // Every queue is empty.
                }
            }
        } finally {
            $this->active = false;
        }

        return $this->result;
    }

    /** Jobs taken so far in this run (counted by the JobProcessing listener). */
    private function jobsTaken(): int
    {
        return $this->result->counts['taken'] ?? 0;
    }

    /** Counts this task's jobs through the queue events (registered once per process). */
    private function listen(): void
    {
        if ($this->listening) {
            return;
        }

        $this->listening = true;

        Event::listen(JobProcessing::class, function (): void {
            if ($this->active) {
                $this->result->add('taken');
            }
        });
        Event::listen(JobProcessed::class, function (): void {
            if ($this->active) {
                $this->result->add('processed');
            }
        });
        Event::listen(JobFailed::class, function (JobFailed $event): void {
            if (!$this->active) {
                return;
            }

            $name = $event->job->resolveName();
            Log::error('A queued job failed in the scheduler run.', [
                'task' => 'worker',
                'job' => $name,
                'queue' => $event->job->getQueue(),
                'error' => $event->exception->getMessage(),
            ]);
            $this->result->add('failed');
            $this->result->error("{$name}: {$event->exception->getMessage()}");
        });
    }
}
