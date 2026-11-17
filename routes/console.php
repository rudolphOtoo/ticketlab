<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('reservations:release-expired')->everyFiveMinutes()->withoutOverlapping();

// Serverless queue worker trigger (processes pending jobs on invocation). The queue list lives in config so a
// test can prove every queue a job is dispatched onto is actually worked.
Schedule::command('queue:work --stop-when-empty --max-time=55 --queue=' . implode(',', config('queue.worker_queues')))
    ->everyMinute()
    ->withoutOverlapping();
