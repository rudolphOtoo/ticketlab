<?php

declare(strict_types=1);

use App\Http\Controllers\Maintenance\RunSchedulerController;
use App\Http\Middleware\VerifySchedulerToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
 | Serverless maintenance hooks, called by an external cron (VerifySchedulerToken).
 |
 | Registered without the "web" group: no session, cookies, CSRF or CSP for a machine caller, so an idle scheduler
 | call costs little more than booting the framework and a few EXISTS queries. The throttle comes first, so wrong
 | tokens are rate limited too.
 */

// cron-job.org: "Authorization: Bearer <SCHEDULER_TOKEN>", or "?token=" when headers cannot be sent.
Route::match(['GET', 'POST'], 'api/run-scheduler', RunSchedulerController::class)->middleware([
    'throttle:scheduler',
    VerifySchedulerToken::class,
]);

// Header only: a URL that runs migrations must never be something that can end up in a log. To be replaced by a
// GitHub Actions migrate step with approval (docs/runbooks/production-deploy.md).
Route::get('api/run-migrate', static function () {
    Artisan::call('migrate', ['--force' => true]);

    return response()->json(['message' => 'Migrations executed', 'output' => Artisan::output()]);
})->middleware(['throttle:10,1', VerifySchedulerToken::class . ':header-only']);
