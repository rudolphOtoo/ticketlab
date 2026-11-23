<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Scheduled work inside one serverless request
 |--------------------------------------------------------------------------
 |
 | On Vercel, cron-job.org calls /api/run-scheduler every 5 minutes (Hobby crons only run daily). The endpoint first
 | checks with a few EXISTS queries whether anything is due and answers at once when nothing is. Otherwise it runs
 | `schedule:run`, which starts each task below as its own process, one after another, inside one request that
 | vercel.json limits to 60 seconds.
 |
 | Every task runs on every call ("everyMinute" means "each time the scheduler is called"): matching a
 | wall-clock minute such as :00 would silently skip tasks whenever the cron fires a minute late. Each task exits after
 | one query when it has nothing to do, and each one's own work selection decides when it is really due.
 |
 | The run has one deadline (run_budget_seconds, passed to each task as SCHEDULER_RUN_DEADLINE). Tasks run most
 | important first and cut their own budgets to what is left, so a busy run skips only the least important work until
 | the next call:
 |
 |   release-expired      a few queries                                     < 1 s
 |   ticket emails        time budget + one PDF and send (8 s SMTP timeout)  6 + 10 s
 |   reconcile-pending    time budget + one Paystack call (timeout)          12 + 8 s
 |   recheck-subaccounts  time budget + one Paystack call (timeout)          5 + 8 s
 |   queue worker (last)  --max-time; a job it is running when the request ends is retried later
 |
 | Overlap locks expire after a few minutes, so a request killed by the platform never blocks a task for long, and
 | they live in a shared cache store (database or Redis), never the per-instance file store.
 |
 */

return [
    'function_max_duration' => 60,

    // The whole scheduler run (all tasks) must end by then; the rest of the 60 s is margin for booting and the response.
    'run_budget_seconds' => (int) env('SCHEDULER_RUN_BUDGET', 50),

    // Cache store holding the withoutOverlapping() and command locks. Must be shared across serverless instances.
    'lock_store' => env('SCHEDULER_LOCK_STORE', 'database'),

    'reconcile' => [
        'batch_size' => (int) env('RECONCILE_BATCH_SIZE', 25),
        'time_budget_seconds' => (int) env('RECONCILE_TIME_BUDGET', 12),
        // Pending orders younger than this are left to the webhook and the buyer's return (holds last 15 minutes).
        'older_than_minutes' => 10,
    ],

    'ticket_emails' => [
        'batch_size' => (int) env('TICKET_EMAIL_BATCH_SIZE', 20),
        'time_budget_seconds' => (int) env('TICKET_EMAIL_TIME_BUDGET', 6),
    ],

    'payout_recheck' => [
        'batch_size' => (int) env('PAYOUT_RECHECK_BATCH_SIZE', 20),
        'time_budget_seconds' => (int) env('PAYOUT_RECHECK_TIME_BUDGET', 5),
        // A zone is due for a re-check once its last check is older than this.
        'interval_hours' => 20,
    ],

    'worker' => [
        'max_time_seconds' => (int) env('SCHEDULER_WORKER_MAX_TIME', 12),
        'max_jobs' => (int) env('SCHEDULER_WORKER_MAX_JOBS', 50),
    ],

    // Paystack calls made by scheduled tasks never wait longer than this (Guzzle's default is 30 s).
    'paystack_timeout_seconds' => 8,
];
