<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 | cron-job.org calls the scheduler every 5 minutes, and every task below runs on each call, most important first
 | (config/scheduler.php explains the time budget). Each task exits after one query when it has nothing to do.
 | Overlap locks live in the shared lock store and expire after a few minutes, so a request the platform kills never
 | blocks a task for long (Laravel's default lock lasts 24 hours).
 */
Schedule::useCache(config('scheduler.lock_store'));

// Seats of holds older than 15 minutes go back on sale. Checkout also reclaims a tier's expired holds itself when
// stock runs short, so a sell-out never waits for this run.
Schedule::command('reservations:release-expired')->everyMinute()->withoutOverlapping(5);

// Ticket emails are sent right after payment is confirmed; this retries only the ones still owed.
Schedule::command('tickets:email-pending')->everyMinute()->withoutOverlapping(5);

// Settles orders whose webhook never arrived: confirms paid ones, releases seats of failed or abandoned ones.
// Takes the oldest pending orders first, at most a batch per run, and stops at its time budget.
Schedule::command('payments:reconcile-pending --execute --older-than='
. (int) config('scheduler.reconcile.older_than_minutes'))
    ->everyMinute()
    ->withoutOverlapping(10);

// Payout subaccounts are verified when saved; this re-checks zones not checked for interval_hours, a small batch per
// run, so every zone is re-verified about once a day without one long run.
Schedule::command('payouts:recheck-subaccounts')->everyMinute()->withoutOverlapping(10);

// Serverless queue worker, last: if the request runs out of time, the job it is working on is simply retried later.
// The queue list lives in config so a test can prove every queue a job is dispatched onto is actually worked.
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
