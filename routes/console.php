<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 | Every task below runs inside one serverless request (see config/scheduler.php for the time budget). Overlap locks
 | live in the shared lock store and expire after a few minutes, so a request the platform kills never blocks a task
 | for long (Laravel's default lock lasts 24 hours).
 */
Schedule::useCache(config('scheduler.lock_store'));

Schedule::command('reservations:release-expired')->everyFiveMinutes()->withoutOverlapping(5);

// Settles orders whose webhook never arrived: confirms paid ones, releases seats of failed or abandoned ones.
// Takes the oldest pending orders first, at most a batch per run, and stops at its time budget.
Schedule::command('payments:reconcile-pending --older-than=10 --execute')->everyTenMinutes()->withoutOverlapping(10);

// Payout subaccounts are verified when saved; this re-checks the zones checked longest ago, a small batch each hour,
// so every zone is re-verified about once a day without one long run.
Schedule::command('payouts:recheck-subaccounts')->hourly()->withoutOverlapping(10);

// Serverless queue worker. The queue list lives in config so a test can prove every queue a job is dispatched onto
// is actually worked; --max-time and --max-jobs keep it inside the request's time budget.
Schedule::command(
    'queue:work --stop-when-empty'
        . ' --max-time='
        . (int) config('scheduler.worker.max_time_seconds')
        . ' --max-jobs='
        . (int) config('scheduler.worker.max_jobs')
        . ' --queue='
        . implode(',', config('queue.worker_queues')),
)
    ->everyMinute()
    ->withoutOverlapping(2);
