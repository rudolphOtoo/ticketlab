<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | Track 2: Ground Operations, Check-In & Financial Ledger Routes
 |--------------------------------------------------------------------------
 | Dedicated route file for Developer B.
 | Handles gate usher camera QR scanner, on-site walk-in cash desk,
 | daily session closure PDF statements, and scoped attendee rosters.
 */

Route::middleware(['auth'])
    ->prefix('ops')
    ->name('ops.')
    ->group(function () {
        // Mobile camera door scanner terminal
        // Route::get('scanner', ScannerTerminal::class)->name('scanner');
        // On-site walk-in cash desk portal
        // Route::get('cash-desk', CashDeskTerminal::class)->name('cash-desk');
    });

Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Daily session ledger declaration & lockdown
        // Route::get('sessions', DailySessionLedger::class)->name('sessions');
        // Scoped attendee rosters & Excel export
        // Route::get('attendees', AttendeeRoster::class)->name('attendees');
    });
