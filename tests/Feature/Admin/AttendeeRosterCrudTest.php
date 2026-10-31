<?php

declare(strict_types=1);

use App\Livewire\Admin\AttendeeRoster;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('admin can manually issue complimentary pass with tag chips', function () {
    $org = Organization::create([
        'name' => 'Accra VIP Org',
        'slug' => 'accra-vip-' . uniqid(),
    ]);

    $admin = User::forceCreate([
        'name' => 'Admin Kofi',
        'email' => 'kofi_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'role' => 'admin',
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'VIP Tech Gala',
        'slug' => 'vip-gala-' . uniqid(),
        'venue' => 'Kempinski Hotel',
        'start_at' => now(),
        'end_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    $tier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP Guest',
        'price' => 0.00,
        'total_capacity' => 50,
        'available_quantity' => 50,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(AttendeeRoster::class)
        ->call('openManualPassModal')
        ->set('manual_name', 'Dr. Kwame Nkrumah')
        ->set('manual_email', 'kwame@ghana.test')
        ->set('manual_phone', '+233201234567')
        ->set('manual_ticket_type_id', $tier->id)
        ->set('newManualTagInput', 'VIP Speaker')
        ->call('addManualTag')
        ->call('issueManualPass')
        ->assertHasNoErrors()
        ->assertSet('isManualPassModalOpen', false);

    $attendee = Attendee::query()->where('email', 'kwame@ghana.test')->first();
    expect($attendee)->not->toBeNull();
    expect($attendee->name)->toBe('Dr. Kwame Nkrumah');
    expect($attendee->custom_fields['tags'])->toContain('VIP Speaker');
    expect($tier->fresh()->available_quantity)->toBe(49);
});

test('admin can edit attendee details and update tags', function () {
    $org = Organization::create([
        'name' => 'Test Org',
        'slug' => 'test-org-' . uniqid(),
    ]);

    $admin = User::forceCreate([
        'name' => 'Admin Ama',
        'email' => 'ama_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'role' => 'admin',
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Summit 2026',
        'slug' => 'summit-' . uniqid(),
        'start_at' => now(),
        'end_at' => now()->addDay(),
        'status' => 'published',
    ]);

    $tier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Standard',
        'price' => 50.00,
        'total_capacity' => 100,
        'available_quantity' => 99,
        'is_active' => true,
    ]);

    $order = \App\Models\Order::create([
        'organization_id' => $org->id,
        'reference' => 'TEST-ORD-' . uniqid(),
        'customer_name' => 'Initial Name',
        'customer_email' => 'initial@test.com',
        'customer_phone' => '123456',
        'gross_amount' => 50.00,
        'net_amount' => 50.00,
        'ticket_quantity' => 1,
        'payment_status' => \App\Enums\OrderStatus::Paid,
        'payment_method' => \App\Enums\PaymentMethod::Card,
    ]);

    $attendee = Attendee::create([
        'organization_id' => $org->id,
        'order_id' => $order->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Initial Name',
        'email' => 'initial@test.com',
        'phone' => '123456',
        'qr_code_hash' => hash('sha256', 'hash-1'),
    ]);

    Livewire::actingAs($admin)
        ->test(AttendeeRoster::class)
        ->call('openEditAttendeeModal', $attendee->id)
        ->set('edit_name', 'Updated Name')
        ->set('edit_email', 'updated@test.com')
        ->set('newEditTagInput', 'Media')
        ->call('addEditTag')
        ->call('updateAttendee')
        ->assertHasNoErrors()
        ->assertSet('isEditModalOpen', false);

    $attendee->refresh();
    expect($attendee->name)->toBe('Updated Name');
    expect($attendee->email)->toBe('updated@test.com');
    expect($attendee->custom_fields['tags'])->toContain('Media');
});
