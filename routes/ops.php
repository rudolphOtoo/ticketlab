<?php

declare(strict_types=1);

use App\Livewire\Admin\AttendeeRoster;
use App\Livewire\Admin\DailySessionLedger;
use App\Livewire\Admin\DataExportCentral;
use App\Livewire\Admin\EventCustomizer;
use App\Livewire\Admin\TicketManager;
use App\Livewire\Ops\CashDeskTerminal;
use App\Livewire\Ops\ScannerTerminal;
use App\Livewire\Platform\PlatformDashboard;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | Track 2: Ground Operations, Check-In & Financial Ledger Routes
 |--------------------------------------------------------------------------
 | Dedicated route file for Developer B.
 | Handles gate usher camera QR scanner, on-site walk-in cash desk,
 | daily session closure PDF statements, and scoped attendee rosters.
 */

Route::middleware(['auth', 'super_admin'])
    ->get('platform', PlatformDashboard::class)
    ->name('platform.dashboard');

Route::middleware(['auth'])
    ->prefix('ops')
    ->name('ops.')
    ->group(function () {
        // Mobile camera door scanner terminal
        Route::get('scanner', ScannerTerminal::class)->name('scanner');

        // On-site walk-in cash desk portal
        Route::get('cash-desk', CashDeskTerminal::class)->name('cash-desk');
    });

Route::middleware(['auth', 'subdivision.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Root admin route redirects to dashboard
        Route::get('/', function () {
            return redirect()->route('dashboard');
        })->name('index');

        // Daily session ledger declaration & lockdown
        Route::get('sessions', DailySessionLedger::class)->name('sessions');

        // Scoped attendee rosters & Excel export
        Route::get('attendees', AttendeeRoster::class)->name('attendees');

        // Self-service ticket category & pricing manager
        Route::get('tickets', TicketManager::class)->name('tickets');

        // Non-coder visual event customizer studio
        Route::get('studio', EventCustomizer::class)->name('studio');

        // Anti-vendor-lock-in data export & sovereignty central
        Route::get('exports', DataExportCentral::class)->name('exports');
    });
