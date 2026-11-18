<?php

declare(strict_types=1);

use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\Platform\OnboardOrganization;
use App\Livewire\Platform\Organizations;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | Platform super admin: organisation onboarding
 |--------------------------------------------------------------------------
 | The platform owners onboard each church or organisation: its zones, their Paystack payout subaccounts,
 | and its first admin, who accepts a signed, single-use invitation to set a password.
 */

Route::middleware(['auth', 'super_admin'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        Route::get('organizations', Organizations::class)->name('organizations');
        Route::get('organizations/create', OnboardOrganization::class)->name('organizations.create');
    });

Route::get('invitations/{user}/accept', AcceptInvitation::class)->middleware([
    'guest',
    'signed',
    'throttle:20,1',
])->name('invitations.accept');
