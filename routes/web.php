<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DedicatedPageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\QrCodeController;
use App\Http\Middleware\VerifySchedulerToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Scannable QR renderer for the Marketing Studio. Rendering is CPU-bound, so it is staff-only and rate limited.
Route::get('api/qr', QrCodeController::class)->middleware(['auth', 'throttle:300,1'])->name('api.qr');

Route::middleware(['auth'])->group(function () {
    // Staff are redirected to their own surface; attendees land on their passes.
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

// Dedicated-mode marketing sub-pages (404 while the platform runs in multi-tenant mode).
Route::get('about-us', DedicatedPageController::class)->defaults('page', 'about')->name('dedicated.about');
Route::get('speakers', DedicatedPageController::class)->defaults('page', 'speakers')->name('dedicated.speakers');
Route::get('conference-packages', DedicatedPageController::class)->defaults('page', 'packages')->name(
    'dedicated.packages',
);
Route::get('highlights', DedicatedPageController::class)->defaults('page', 'highlights')->name('dedicated.highlights');
Route::get('gallery', DedicatedPageController::class)->defaults('page', 'gallery')->name('dedicated.gallery');
Route::get('partnership', DedicatedPageController::class)->defaults('page', 'partnership')->name(
    'dedicated.partnership',
);
Route::get('faq', DedicatedPageController::class)->defaults('page', 'faq')->name('dedicated.faq');
Route::get('contact', DedicatedPageController::class)->defaults('page', 'contact')->name('dedicated.contact');

// Serverless maintenance hooks, called by an external cron. Token in "Authorization: Bearer" (see VerifySchedulerToken).
Route::middleware([VerifySchedulerToken::class, 'throttle:10,1'])->group(static function (): void {
    Route::get('api/run-scheduler', static function (): JsonResponse {
        Artisan::call('schedule:run');

        return response()->json(['message' => 'Scheduler executed', 'output' => Artisan::output()]);
    });

    Route::get('api/run-migrate', static function (): JsonResponse {
        Artisan::call('migrate', ['--force' => true]);

        return response()->json(['message' => 'Migrations executed', 'output' => Artisan::output()]);
    });
});

require __DIR__ . '/settings.php';
