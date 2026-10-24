<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | Track 1: Public Buyer & Paystack Checkout Routes
 |--------------------------------------------------------------------------
 | Dedicated route file for Developer A.
 | Handles public event landing, ticket selection, Paystack InlineJS checkout,
 | and digital QR pass issuance.
 */

Route::prefix('{org_slug}')
    ->name('public.')
    ->group(function () {
        // Event landing & ticket tier selection
        // Route::get('{event_slug}', EventShow::class)->name('event.show');
    });

Route::prefix('tickets')
    ->name('public.tickets.')
    ->group(function () {
        // Ticket confirmation & pass display
        // Route::get('{order:reference}', TicketShow::class)->name('show');
    });
