<?php

declare(strict_types=1);

use App\Http\Controllers\Payment\PaystackWebhookController;
use App\Livewire\Public\EventDirectory;
use App\Livewire\Public\EventShow;
use App\Livewire\Public\TicketShow;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | Track 1: Public Buyer & Paystack Checkout Routes
 |--------------------------------------------------------------------------
 | Dedicated route file for Developer A.
 | Handles public event landing, ticket selection, Paystack InlineJS checkout,
 | and digital QR pass issuance.
 */

Route::post('api/paystack/webhook', [PaystackWebhookController::class, 'handle'])
    ->name('public.paystack.webhook')
    ->withoutMiddleware([ValidateCsrfToken::class]);

Route::post('api/payzephyr/webhook', [PaystackWebhookController::class, 'handle'])
    ->name('public.payzephyr.webhook')
    ->withoutMiddleware([ValidateCsrfToken::class]);

Route::get('events', EventDirectory::class)->name('public.events.index');

Route::get('tickets/{order:reference}', TicketShow::class)->name('public.tickets.show');

Route::prefix('{org_slug}')
    ->name('public.')
    ->where([
        'org_slug' => '(?!ops/|admin/|api/|livewire/|up/|dashboard/|settings/|tickets/|events/)[A-Za-z0-9][A-Za-z0-9-]*',
    ])
    ->group(function () {
        // Event landing, ticket tier selection & Paystack checkout drawer
        Route::get('{event_slug}', EventShow::class)->name('event.show');
    });
