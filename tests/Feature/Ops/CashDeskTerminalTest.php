<?php

declare(strict_types=1);

use App\Livewire\Ops\CashDeskTerminal;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('unauthenticated users cannot view cash desk register', function () {
    $this->get(route('ops.cash-desk'))->assertRedirect(route('login'));
});

test('authenticated user can view cash desk register with tiers', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Usher John',
        'email' => 'usher_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Kingdom Summit 2026',
        'slug' => 'summit-' . uniqid(),
        'venue' => 'Perez Dome',
        'start_at' => now(),
        'end_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP Gold Pass',
        'price' => 120.00,
        'total_capacity' => 500,
        'available_quantity' => 486,
    ]);

    $this
        ->actingAs($user)
        ->get(route('ops.cash-desk'))
        ->assertOk()
        ->assertSee('Walk-In Cash Desk')
        ->assertSee('VIP Gold Pass')
        ->assertSee('GHS 120.00');
});

test('cash desk terminal calculates total and issues pass with change', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Usher John',
        'email' => 'usher_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Kingdom Summit 2026',
        'slug' => 'summit-' . uniqid(),
        'venue' => 'Perez Dome',
        'start_at' => now(),
        'end_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    $tier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP Gold Pass',
        'price' => 120.00,
        'total_capacity' => 500,
        'available_quantity' => 486,
    ]);

    Livewire::actingAs($user)
        ->test(CashDeskTerminal::class, ['eventId' => $event->id])
        ->call('selectTier', $tier->id)
        ->set('quantity', 2)
        ->set('cashTendered', '300.00')
        ->set('attendeeName', 'Kwesi Arthur')
        ->call('processCashSale')
        ->assertHasNoErrors()
        ->assertDispatched('pass-issued')
        ->assertSet('issuedPass.attendee_name', 'Kwesi Arthur')
        ->assertSet('issuedPass.total_amount', 240.00)
        ->assertSet('issuedPass.change_due', 60.00);
});
