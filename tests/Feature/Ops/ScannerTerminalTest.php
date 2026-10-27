<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Livewire\Ops\ScannerTerminal;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('unauthenticated users cannot view gate door scanner', function () {
    $this->get(route('ops.scanner'))->assertRedirect(route('login'));
});

test('authenticated usher can view scanner terminal with telemetry and viewfinder', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Usher Kwame',
        'email' => 'usher_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $this
        ->actingAs($user)
        ->get(route('ops.scanner'))
        ->assertOk()
        ->assertSee('Gate 04 · Door Scanner')
        ->assertSee('Optical Viewfinder Ready');
});

test('scanner terminal validates valid qr pass atomically and stamps check in', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Usher Kwame',
        'email' => 'usher_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $subdivision = Subdivision::create([
        'organization_id' => $org->id,
        'name' => 'VIP Lounge Sector A',
        'slug' => 'vip-a-' . uniqid(),
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
        'organization_id' => $org->id,
        'subdivision_id' => $subdivision->id,
        'reference' => 'TL-ORD-' . uniqid(),
        'customer_name' => 'Kojo Antwi',
        'customer_email' => 'kojo@music.gh',
        'customer_phone' => '+233244999888',
        'gross_amount' => 120.00,
        'net_amount' => 120.00,
        'fee_amount' => 0.00,
        'payment_method' => PaymentMethod::PhysicalCash->value,
        'payment_status' => OrderStatus::Paid->value,
        'paid_at' => now(),
    ]);

    $validHash = hash('sha256', 'TL-PASS-TEST-12345');

    $attendee = Attendee::create([
        'organization_id' => $org->id,
        'order_id' => $order->id,
        'subdivision_id' => $subdivision->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Kojo Antwi',
        'email' => 'kojo@music.gh',
        'phone' => '+233244999888',
        'qr_code_hash' => $validHash,
        'checked_in_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test(ScannerTerminal::class, ['eventId' => $event->id])
        ->call('validatePass', $validHash)
        ->assertSet('scanState', 'success')
        ->assertSet('scanResult.status', 'VERIFIED')
        ->assertSet('scanResult.name', 'Kojo Antwi')
        ->assertSet('scanResult.tier', 'VIP Gold Pass')
        ->assertSet('scanResult.zone', 'VIP Lounge Sector A')
        ->assertDispatched('play-success-sound');

    $attendee->refresh();
    expect($attendee->checked_in_at)->not->toBeNull()->and($attendee->checked_in_by)->toBe($user->id);
});

test('scanner terminal detects duplicate scan and warns with prior timestamp', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Usher Kwame',
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
        'name' => 'Regular Pass',
        'price' => 50.00,
        'total_capacity' => 1000,
        'available_quantity' => 950,
    ]);

    $order = Order::create([
        'organization_id' => $org->id,
        'reference' => 'TL-ORD-' . uniqid(),
        'customer_name' => 'Ama Mansa',
        'customer_email' => 'ama@test.gh',
        'customer_phone' => '+233201112233',
        'gross_amount' => 50.00,
        'net_amount' => 50.00,
        'fee_amount' => 0.00,
        'payment_method' => PaymentMethod::PhysicalCash->value,
        'payment_status' => OrderStatus::Paid->value,
        'paid_at' => now(),
    ]);

    $dupeHash = hash('sha256', 'TL-PASS-DUPE-999');
    $earlierScanTime = now()->subMinutes(20);

    $attendee = Attendee::create([
        'organization_id' => $org->id,
        'order_id' => $order->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Ama Mansa',
        'email' => 'ama@test.gh',
        'phone' => '+233201112233',
        'qr_code_hash' => $dupeHash,
        'checked_in_at' => $earlierScanTime,
    ]);

    Livewire::actingAs($user)
        ->test(ScannerTerminal::class, ['eventId' => $event->id])
        ->call('validatePass', $dupeHash)
        ->assertSet('scanState', 'duplicate')
        ->assertSet('scanResult.status', 'DUPLICATE')
        ->assertSet('scanResult.name', 'Ama Mansa')
        ->assertDispatched('play-warning-sound');

    $attendee->refresh();
    expect($attendee->checked_in_at->timestamp)->toBe($earlierScanTime->timestamp);
});

test('scanner terminal rejects invalid or forged qr hash', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::create([
        'name' => 'Usher Kwame',
        'email' => 'usher_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    Livewire::actingAs($user)
        ->test(ScannerTerminal::class)
        ->call('validatePass', 'UNKNOWN_FORGED_HASH_CODE')
        ->assertSet('scanState', 'invalid')
        ->assertSet('scanResult.status', 'INVALID')
        ->assertDispatched('play-error-sound');
});

test('scanner terminal enforces tenant isolation and rejects pass from another organization', function () {
    $orgA = Organization::create([
        'name' => 'Org Alpha',
        'slug' => 'org-alpha-' . uniqid(),
    ]);

    $orgB = Organization::create([
        'name' => 'Org Beta',
        'slug' => 'org-beta-' . uniqid(),
    ]);

    $userA = User::create([
        'name' => 'Usher Alpha',
        'email' => 'usher_a_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $orgA->id,
    ]);

    $eventB = Event::create([
        'organization_id' => $orgB->id,
        'title' => 'Beta Event',
        'slug' => 'beta-event-' . uniqid(),
        'venue' => 'Beta Center',
        'start_at' => now(),
        'end_at' => now()->addDays(1),
        'status' => 'published',
    ]);

    $tierB = TicketType::create([
        'event_id' => $eventB->id,
        'name' => 'Beta Pass',
        'price' => 100.00,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);

    $orderB = Order::create([
        'organization_id' => $orgB->id,
        'reference' => 'TL-ORD-B-' . uniqid(),
        'customer_name' => 'Foreign Guest',
        'customer_email' => 'foreign@beta.com',
        'customer_phone' => '+233555555555',
        'gross_amount' => 100.00,
        'net_amount' => 100.00,
        'fee_amount' => 0.00,
        'payment_method' => PaymentMethod::PhysicalCash->value,
        'payment_status' => OrderStatus::Paid->value,
        'paid_at' => now(),
    ]);

    $hashOrgB = hash('sha256', 'TL-PASS-ORG-B');

    Attendee::create([
        'organization_id' => $orgB->id,
        'order_id' => $orderB->id,
        'ticket_type_id' => $tierB->id,
        'name' => 'Foreign Guest',
        'email' => 'foreign@beta.com',
        'phone' => '+233555555555',
        'qr_code_hash' => $hashOrgB,
        'checked_in_at' => null,
    ]);

    // Usher from Org A scans pass from Org B -> Must be rejected as INVALID
    Livewire::actingAs($userA)
        ->test(ScannerTerminal::class)
        ->call('validatePass', $hashOrgB)
        ->assertSet('scanState', 'invalid')
        ->assertSet('scanResult.status', 'INVALID')
        ->assertDispatched('play-error-sound');
});
