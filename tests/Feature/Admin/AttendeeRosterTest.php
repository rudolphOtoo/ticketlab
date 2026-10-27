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

test('door terminal usher is blocked by middleware from accessing administrative roster', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $usher = User::create([
        'name' => 'Gate Usher',
        'email' => 'usher_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'role' => 'usher',
    ]);

    $this->actingAs($usher)->get(route('admin.attendees'))->assertForbidden();
});

test('authenticated admin can view attendee roster with list of attendees', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Admin User',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'role' => 'admin',
    ]);

    $sub = Subdivision::create([
        'organization_id' => $org->id,
        'name' => 'Accra Central',
        'slug' => 'accra-central-' . uniqid(),
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

    $order = Order::create([
        'reference' => 'ORD-' . uniqid(),
        'organization_id' => $org->id,
        'subdivision_id' => $sub->id,
        'customer_name' => 'Ama Serwah',
        'customer_email' => 'ama@example.com',
        'customer_phone' => '0241234567',
        'gross_amount' => 120.00,
        'net_amount' => 120.00,
        'fee_amount' => 0.00,
        'payment_status' => 'paid',
        'payment_method' => 'card',
    ]);

    Attendee::create([
        'order_id' => $order->id,
        'organization_id' => $org->id,
        'subdivision_id' => $sub->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Ama Serwah',
        'email' => 'ama@example.com',
        'phone' => '0241234567',
        'qr_code_hash' => 'HASH_AMA_' . uniqid(),
    ]);

    $this
        ->actingAs($user)
        ->get(route('admin.attendees'))
        ->assertOk()
        ->assertSee('Attendee Roster')
        ->assertSee('Ama Serwah')
        ->assertSee('VIP Gold Pass');
});

test('zone admin is strictly scoped to their assigned subdivision', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $zoneA = Subdivision::create([
        'organization_id' => $org->id,
        'name' => 'Kumasi Zone',
        'slug' => 'kumasi-' . uniqid(),
    ]);

    $zoneB = Subdivision::create([
        'organization_id' => $org->id,
        'name' => 'Accra Zone',
        'slug' => 'accra-' . uniqid(),
    ]);

    $zoneAdminA = User::create([
        'name' => 'Kumasi Head',
        'email' => 'kumasi_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'subdivision_id' => $zoneA->id,
        'role' => 'admin',
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
        'name' => 'Standard Ticket',
        'price' => 50.00,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);

    $orderA = Order::create([
        'reference' => 'ORD-A-' . uniqid(),
        'organization_id' => $org->id,
        'subdivision_id' => $zoneA->id,
        'customer_name' => 'Kumasi Attendee',
        'customer_email' => 'kumasi@test.gh',
        'customer_phone' => '0241111111',
        'gross_amount' => 50.00,
        'net_amount' => 50.00,
        'payment_status' => 'paid',
        'payment_method' => 'cash',
    ]);

    $orderB = Order::create([
        'reference' => 'ORD-B-' . uniqid(),
        'organization_id' => $org->id,
        'subdivision_id' => $zoneB->id,
        'customer_name' => 'Accra Attendee',
        'customer_email' => 'accra@test.gh',
        'customer_phone' => '0242222222',
        'gross_amount' => 50.00,
        'net_amount' => 50.00,
        'payment_status' => 'paid',
        'payment_method' => 'cash',
    ]);

    Attendee::create([
        'order_id' => $orderA->id,
        'organization_id' => $org->id,
        'subdivision_id' => $zoneA->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Kumasi Attendee',
        'email' => 'kumasi@test.gh',
        'phone' => '0241111111',
        'qr_code_hash' => 'HASH_KUMASI_' . uniqid(),
    ]);

    Attendee::create([
        'order_id' => $orderB->id,
        'organization_id' => $org->id,
        'subdivision_id' => $zoneB->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Accra Attendee',
        'email' => 'accra@test.gh',
        'phone' => '0242222222',
        'qr_code_hash' => 'HASH_ACCRA_' . uniqid(),
    ]);

    // Kumasi Zone Admin sees only Kumasi Attendee
    Livewire::actingAs($zoneAdminA)
        ->test(AttendeeRoster::class)
        ->assertSee('Kumasi Attendee')
        ->assertDontSee('Accra Attendee');
});

test('attendee check-in status can be toggled', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Admin User',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'role' => 'admin',
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
        'name' => 'Regular Pass',
        'price' => 50.00,
        'total_capacity' => 500,
        'available_quantity' => 486,
    ]);

    $order = Order::create([
        'reference' => 'ORD-' . uniqid(),
        'organization_id' => $org->id,
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
        'qr_code_hash' => 'HASH_KOFI_' . uniqid(),
    ]);

    expect($attendee->isCheckedIn())->toBeFalse();

    Livewire::actingAs($user)->test(AttendeeRoster::class)->call('toggleCheckIn', $attendee->id);

    expect($attendee->fresh()->isCheckedIn())->toBeTrue();

    Livewire::actingAs($user)->test(AttendeeRoster::class)->call('toggleCheckIn', $attendee->id);

    expect($attendee->fresh()->isCheckedIn())->toBeFalse();
});

test('attendee roster exports streaming CSV with spatie simple excel', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Super Admin',
        'email' => 'super_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'role' => 'admin',
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

    $order = Order::create([
        'reference' => 'ORD-EXP-' . uniqid(),
        'organization_id' => $org->id,
        'customer_name' => 'Export Guest',
        'customer_email' => 'export@test.gh',
        'customer_phone' => '0249999999',
        'gross_amount' => 120.00,
        'net_amount' => 120.00,
        'payment_status' => 'paid',
        'payment_method' => 'card',
    ]);

    Attendee::create([
        'order_id' => $order->id,
        'organization_id' => $org->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Export Guest',
        'email' => 'export@test.gh',
        'phone' => '0249999999',
        'qr_code_hash' => 'HASH_EXP_' . uniqid(),
    ]);

    Livewire::actingAs($user)->test(AttendeeRoster::class)->call('exportCsv')->assertFileDownloaded();
});

test('attendee roster strictly isolates records across different organizations', function () {
    $orgA = Organization::create([
        'name' => 'Org Alpha',
        'slug' => 'org-alpha-' . uniqid(),
    ]);

    $orgB = Organization::create([
        'name' => 'Org Beta',
        'slug' => 'org-beta-' . uniqid(),
    ]);

    $adminA = User::create([
        'name' => 'Admin Alpha',
        'email' => 'alpha_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $orgA->id,
        'role' => 'admin',
    ]);

    $eventA = Event::create([
        'organization_id' => $orgA->id,
        'title' => 'Event Alpha',
        'slug' => 'event-alpha-' . uniqid(),
        'venue' => 'Arena A',
        'start_at' => now(),
        'end_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    $tierA = TicketType::create([
        'event_id' => $eventA->id,
        'name' => 'Alpha Pass',
        'price' => 50.00,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);

    $orderA = Order::create([
        'reference' => 'ORD-A-' . uniqid(),
        'organization_id' => $orgA->id,
        'customer_name' => 'Guest Alpha',
        'customer_email' => 'alpha@test.gh',
        'customer_phone' => '0241111111',
        'gross_amount' => 50.00,
        'net_amount' => 50.00,
        'payment_status' => 'paid',
        'payment_method' => 'cash',
    ]);

    Attendee::create([
        'order_id' => $orderA->id,
        'organization_id' => $orgA->id,
        'ticket_type_id' => $tierA->id,
        'name' => 'Guest Alpha Unique',
        'email' => 'alpha@test.gh',
        'phone' => '0241111111',
        'qr_code_hash' => 'HASH_ALPHA_' . uniqid(),
    ]);

    $orderB = Order::create([
        'reference' => 'ORD-B-' . uniqid(),
        'organization_id' => $orgB->id,
        'customer_name' => 'Guest Beta',
        'customer_email' => 'beta@test.gh',
        'customer_phone' => '0242222222',
        'gross_amount' => 50.00,
        'net_amount' => 50.00,
        'payment_status' => 'paid',
        'payment_method' => 'cash',
    ]);

    Attendee::create([
        'order_id' => $orderB->id,
        'organization_id' => $orgB->id,
        'ticket_type_id' => $tierA->id,
        'name' => 'Guest Beta Secret',
        'email' => 'beta@test.gh',
        'phone' => '0242222222',
        'qr_code_hash' => 'HASH_BETA_' . uniqid(),
    ]);

    Livewire::actingAs($adminA)
        ->test(AttendeeRoster::class)
        ->assertSee('Guest Alpha Unique')
        ->assertDontSee('Guest Beta Secret');
});
