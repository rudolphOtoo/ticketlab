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

    $user = User::factory()->create([
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

    $user = User::factory()->create([
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

    // Verify database inventory decrement and record creation
    $tier->refresh();
    expect($tier->available_quantity)->toBe(484);

    $this->assertDatabaseHas('orders', [
        'organization_id' => $org->id,
        'customer_name' => 'Kwesi Arthur',
        'gross_amount' => 240.00,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
    ]);

    $this->assertDatabaseCount('attendees', 2);
});

test('cash desk terminal rejects sale when requested quantity exceeds available capacity', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::factory()->create([
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
        'total_capacity' => 2,
        'available_quantity' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(CashDeskTerminal::class, ['eventId' => $event->id])
        ->call('selectTier', $tier->id)
        ->set('quantity', 2)
        ->set('cashTendered', '300.00')
        ->set('attendeeName', 'Late Guest')
        ->call('processCashSale')
        ->assertHasErrors(['tier']);

    $tier->refresh();
    expect($tier->available_quantity)->toBe(1);
    $this->assertDatabaseCount('orders', 0);
});

test('cash desk terminal rejects non-positive or excessive ticket quantities', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::factory()->create([
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
        'total_capacity' => 50,
        'available_quantity' => 50,
    ]);

    Livewire::actingAs($user)
        ->test(CashDeskTerminal::class, ['eventId' => $event->id])
        ->call('selectTier', $tier->id)
        ->set('quantity', 0)
        ->set('cashTendered', '300.00')
        ->call('processCashSale')
        ->assertHasErrors(['quantity']);
});
