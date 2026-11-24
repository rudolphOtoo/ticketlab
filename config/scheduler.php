<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Scheduled work inside one serverless request
 |--------------------------------------------------------------------------
 |
 | On Vercel, cron-job.org calls /api/run-scheduler every 5 minutes. The endpoint (App\Services\Scheduler\SchedulerRunner)
 | first asks each task whether it has anything to do: a few indexed EXISTS queries. When nothing is due it answers
 | "idle" at once. Otherwise it takes one run lock and runs the due tasks in this process, most important first:
 |
 |   release_holds        expired seat holds back on sale
 |   reconcile            pending payments whose webhook never came (Paystack verify)
 |   ticket_emails        ticket emails still owed (Brevo daily cap respected)
 |   worker               queued jobs: transactional, emails, broadcasts, default
 |   payout_recheck       zones' Paystack subaccounts, about once a day each
 |   cleanup              expired cache rows, old scheduler runs
 |
 | The run has one deadline, run_budget_seconds. Each task gets min(its budget_seconds, time left - its
 | overrun_seconds): budget_seconds is when it stops starting new items, overrun_seconds is the longest one item can
 | take once started (a Paystack call, one PDF and send). A task that does not fit is skipped and reported; its work
 | waits for the next call. So the run always ends by the deadline, which leaves at least 10 s of the function limit
 | for booting and the response.
 |
 | Vercel plan                      Hobby (now)   Pro
 |   function_max_duration          60            120   (also "maxDuration" of api/scheduler.php in vercel.json)
 |   run_budget_seconds             48            105   (SCHEDULER_RUN_BUDGET)
 |
 | Upgrading is config-only: change both values above and the one in vercel.json (a test keeps them in step).
 |
 */

return [
    // Seconds Vercel lets the scheduler function run (vercel.json, api/scheduler.php). Pro: 120.
    'function_max_duration' => 60,

    // The whole run must end by then. At most function_max_duration - 10. Pro: 105.
    'run_budget_seconds' => (int) env('SCHEDULER_RUN_BUDGET', 48),

    // Cache store holding the run lock. Must be shared across serverless instances: database or redis, never file.
    'lock_store' => env('SCHEDULER_LOCK_STORE', 'database'),

    'release_holds' => [
        'batch_size' => 500,
        'time_budget_seconds' => 5,
        'overrun_seconds' => 2,
    ],

    'reconcile' => [
        'batch_size' => (int) env('RECONCILE_BATCH_SIZE', 25),
        'time_budget_seconds' => (int) env('RECONCILE_TIME_BUDGET', 12),
        // One Paystack verify (paystack_timeout_seconds).
        'overrun_seconds' => 8,
        // Pending orders younger than this are left to the webhook and the buyer's return (holds last 15 minutes).
        'older_than_minutes' => 10,
    ],

    'ticket_emails' => [
        'batch_size' => (int) env('TICKET_EMAIL_BATCH_SIZE', 20),
        'time_budget_seconds' => (int) env('TICKET_EMAIL_TIME_BUDGET', 6),
        // Longest one attempt may take: rendering the PDF plus one send (MAIL_TIMEOUT).
        'overrun_seconds' => 15,
    ],

    'worker' => [
        'time_budget_seconds' => (int) env('SCHEDULER_WORKER_MAX_TIME', 12),
        'max_jobs' => (int) env('SCHEDULER_WORKER_MAX_JOBS', 50),
        // One job: a send bounded by MAIL_TIMEOUT, or one broadcast chunk.
        'overrun_seconds' => 10,
    ],

    'payout_recheck' => [
        'batch_size' => (int) env('PAYOUT_RECHECK_BATCH_SIZE', 20),
        'time_budget_seconds' => (int) env('PAYOUT_RECHECK_TIME_BUDGET', 5),
        'overrun_seconds' => 8,
        // A zone is due for a re-check once its last check is older than this.
        'interval_hours' => 20,
    ],

    'cleanup' => [
        'batch_size' => 1000,
        'time_budget_seconds' => 3,
        'overrun_seconds' => 2,
        // Expired cache rows are deleted once they have been expired this long (so cleanup is rarely due).
        'expired_cache_grace_hours' => 24,
    ],

    // Paystack calls made by scheduled tasks never wait longer than this (Guzzle's default is 30 s).
    'paystack_timeout_seconds' => 8,
];
