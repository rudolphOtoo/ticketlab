<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DedicatedPageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\QrCodeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Scannable QR renderer for Marketing Studio, vector downloads, and public tickets.
// Rate limited to 60 requests per minute to prevent CPU exhaustion.
Route::get('api/qr', QrCodeController::class)->middleware('throttle:300,1')->name('api.qr');

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

Route::get('api/run-scheduler', function (\Illuminate\Http\Request $request) {
    $expectedToken = env('SCHEDULER_TOKEN');
    if ($expectedToken && $request->query('token') !== $expectedToken) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    \Illuminate\Support\Facades\Artisan::call('schedule:run');

    return response()->json([
        'message' => 'Scheduler executed',
        'output' => \Illuminate\Support\Facades\Artisan::output(),
    ]);
})->middleware('throttle:10,1');

Route::get('api/run-migrate', function (\Illuminate\Http\Request $request) {
    $expectedToken = env('SCHEDULER_TOKEN');
    if (! $expectedToken || $request->query('token') !== $expectedToken) {
        return response()->json(['error' => 'Unauthorized: valid token query parameter required'], 401);
    }

    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

    return response()->json([
        'message' => 'Migrations executed',
        'output' => \Illuminate\Support\Facades\Artisan::output(),
    ]);
})->middleware('throttle:10,1');

require __DIR__ . '/settings.php';
