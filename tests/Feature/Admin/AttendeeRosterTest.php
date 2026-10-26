<?php

declare(strict_types=1);

use App\Livewire\Admin\AttendeeRoster;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('unauthenticated users cannot view attendee roster', function () {
    $this->get(route('admin.attendees'))->assertRedirect(route('login'));
});

test('authenticated user can view attendee roster with list of attendees', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-'.uniqid(),
    ]);

    $user = User::create([
        'name' => 'Admin User',
        'email' => 'admin_'.uniqid().'@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $sub = Subdivision::create([
        'organization_id' => $org->id,
        'name' => 'Accra Central',
        'slug' => 'accra-central-'.uniqid(),
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Kingdom Summit 2026',
        'slug' => 'summit-'.uniqid(),
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

    $order = Order::create([
        'reference' => 'ORD-'.uniqid(),
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'customer_name' => 'Ama Serwah',
        'customer_email' => 'ama@example.com',
        'customer_phone' => '0241234567',
        'gross_amount' => 120.00,
        'net_amount' => 120.00,
        'fee_amount' => 0.00,
        'payment_status' => 'paid',
        'payment_method' => 'card',
    ]);

    $attendee = Attendee::create([
        'order_id' => $order->id,
        'organization_id' => $org->id,
        'subdivision_id' => $sub->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Ama Serwah',
        'email' => 'ama@example.com',
        'phone' => '0241234567',
        'qr_code_hash' => 'HASH_AMA_'.uniqid(),
    ]);

    $this
        ->actingAs($user)
        ->get(route('admin.attendees'))
        ->assertOk()
        ->assertSee('Attendee Roster')
        ->assertSee('Ama Serwah')
        ->assertSee('VIP Gold Pass');
});

test('attendee check-in status can be toggled', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-'.uniqid(),
    ]);

    $user = User::create([
        'name' => 'Admin User',
        'email' => 'admin_'.uniqid().'@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Kingdom Summit 2026',
        'slug' => 'summit-'.uniqid(),
        'venue' => 'Perez Dome',
        'start_at' => now(),
        'end_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    $tier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Regular Pass',
        'price' => 50.00,
        'total_capacity' => 500,
        'available_quantity' => 486,
    ]);

    $order = Order::create([
        'reference' => 'ORD-'.uniqid(),
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'customer_name' => 'Kofi Mensah',
        'customer_email' => 'kofi@example.com',
        'customer_phone' => '0241234568',
        'gross_amount' => 50.00,
        'net_amount' => 50.00,
        'fee_amount' => 0.00,
        'payment_status' => 'paid',
        'payment_method' => 'cash',
    ]);

    $attendee = Attendee::create([
        'order_id' => $order->id,
        'organization_id' => $org->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Kofi Mensah',
        'email' => 'kofi@example.com',
        'phone' => '0241234568',
        'qr_code_hash' => 'HASH_KOFI_'.uniqid(),
    ]);

    expect($attendee->isCheckedIn())->toBeFalse();

    Livewire::actingAs($user)->test(AttendeeRoster::class)->call('toggleCheckIn', $attendee->id);

    expect($attendee->fresh()->isCheckedIn())->toBeTrue();

    Livewire::actingAs($user)->test(AttendeeRoster::class)->call('toggleCheckIn', $attendee->id);

    expect($attendee->fresh()->isCheckedIn())->toBeFalse();
});
