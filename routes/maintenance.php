<?php

declare(strict_types=1);

use App\Http\Controllers\Maintenance\RunSchedulerController;
use App\Http\Middleware\VerifySchedulerToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
 | Serverless maintenance hooks, called by an external cron. Token in "Authorization: Bearer" (VerifySchedulerToken).
 |
 | Registered without the "web" group: no session, cookies, CSRF or CSP for a machine caller, so an idle scheduler
 | call costs little more than booting the framework and a few EXISTS queries.
 */
Route::middleware([VerifySchedulerToken::class, 'throttle:10,1'])->group(static function (): void {
    Route::get('api/run-scheduler', RunSchedulerController::class);

    Route::get('api/run-migrate', static function () {
        Artisan::call('migrate', ['--force' => true]);

        return response()->json(['message' => 'Migrations executed', 'output' => Artisan::output()]);
    });
});
