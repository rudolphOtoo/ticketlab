<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 | On Vercel, cron-job.org calls /api/run-scheduler every 5 minutes and the tasks run in that request
 | (App\Services\Scheduler\SchedulerRunner, config/scheduler.php). On a host with a real cron, `schedule:run` every
 | minute runs the same thing through scheduler:tick. The runner holds its own run lock, so the two never overlap.
 */
Schedule::command('scheduler:tick')->everyMinute();
