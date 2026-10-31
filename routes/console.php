<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('reservations:release-expired')->everyFiveMinutes()->withoutOverlapping();

// Serverless queue worker trigger (processes pending jobs on invocation)
Schedule::command('queue:work --stop-when-empty --max-time=55 --queue=emails,default')
    ->everyMinute()
    ->withoutOverlapping();
