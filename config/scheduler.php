<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Scheduled work inside one serverless request
 |--------------------------------------------------------------------------
 |
 | On Vercel, an external cron calls /api/run-scheduler every minute, and `schedule:run` executes every due task
 | one after another inside that single HTTP request. vercel.json sets the function's maxDuration to 60 seconds, so the
 | worst cases of all tasks that can be due in the same minute must add up to less than that:
 |
 |   reconcile-pending   time budget + one Paystack call (timeout)   15 + 10 = 25 s
 |   recheck-subaccounts time budget + one Paystack call (timeout)    8 +  5 = 13 s
 |   queue worker        --max-time                                       15 s
 |   release-expired     a few queries                                   < 1 s
 |                                                                       ------
 |                                                                       ~54 s  (< 60 s)
 |
 | Work that does not fit waits for the next run: each task takes the oldest items first and stops at its budget.
 | Overlap locks expire after a few minutes, so a request killed by the platform never blocks a task for long, and
 | they live in a shared cache store (database or Redis), never the per-instance file store.
 |
 */

return [
    'function_max_duration' => 60,

    // Cache store holding the withoutOverlapping() and command locks. Must be shared across serverless instances.
    'lock_store' => env('SCHEDULER_LOCK_STORE', 'database'),

    'reconcile' => [
        'batch_size' => (int) env('RECONCILE_BATCH_SIZE', 25),
        'time_budget_seconds' => (int) env('RECONCILE_TIME_BUDGET', 15),
    ],

    'payout_recheck' => [
        'batch_size' => (int) env('PAYOUT_RECHECK_BATCH_SIZE', 20),
        'time_budget_seconds' => (int) env('PAYOUT_RECHECK_TIME_BUDGET', 8),
        // A zone is due for a re-check once its last check is older than this.
        'interval_hours' => 20,
    ],

    'worker' => [
        'max_time_seconds' => (int) env('SCHEDULER_WORKER_MAX_TIME', 15),
        'max_jobs' => (int) env('SCHEDULER_WORKER_MAX_JOBS', 50),
    ],

    // Paystack calls made by scheduled tasks never wait longer than this (Guzzle's default is 30 s).
    'paystack_timeout_seconds' => 10,
];
