<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reservations:release-expired')->everyFiveMinutes()->withoutOverlapping();
