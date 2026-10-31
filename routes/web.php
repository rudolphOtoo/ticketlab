<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DedicatedPageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\QrCodeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth'])->group(function () {
    // Staff are redirected to their own surface; attendees land on their passes.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Scannable QR renderer for the Marketing Studio canvas and vector/PNG downloads.
    Route::get('api/qr', QrCodeController::class)->middleware('throttle:120,1')->name('api.qr');
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

require __DIR__ . '/settings.php';
